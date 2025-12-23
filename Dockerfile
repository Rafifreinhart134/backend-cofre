# Stage 1: Build PHP dependencies first (needed for Tailwind config)
FROM composer:2 AS vendor

WORKDIR /app
COPY . .
# --ignore-platform-reqs is used because the PHP version in this container might differ from the final one
# We only care about the vendor files here.
RUN composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --ignore-platform-reqs

# Stage 2: Build frontend assets (after vendor is available)
FROM node:18-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .
# Copy vendor from previous stage (needed for tailwind.config.js preset)
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Stage 3: Final production image
FROM php:8.2-fpm

# Install system dependencies, PHP extensions, and Nginx
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_pgsql zip bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP for production
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.interned_strings_buffer=8" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "upload_max_filesize=100M" >> "$PHP_INI_DIR/conf.d/uploads.ini" \
    && echo "post_max_size=100M" >> "$PHP_INI_DIR/conf.d/uploads.ini" \
    && echo "memory_limit=256M" >> "$PHP_INI_DIR/conf.d/memory.ini"

# Remove default Nginx config if it exists
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default

# Copy our custom Nginx config
COPY fly-nginx.conf /etc/nginx/sites-available/default
RUN ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Configure PHP-FPM to use TCP socket instead of unix socket
RUN sed -i 's/listen = \/run\/php\/php-fpm.sock/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration for queue worker
COPY fly-supervisor.conf /etc/supervisor/conf.d/laravel.conf

# Copy built assets from the 'assets' stage
COPY --from=assets /app/public /var/www/html/public

# Copy vendor files from the 'vendor' stage
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy the rest of the application code
COPY . /var/www/html

# Set correct permissions for storage and bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and make entrypoint script executable
COPY fly-entrypoint.sh /fly-entrypoint.sh
RUN chmod +x /fly-entrypoint.sh

# Expose port 8080
EXPOSE 8080

# Set the entrypoint
ENTRYPOINT ["/fly-entrypoint.sh"]