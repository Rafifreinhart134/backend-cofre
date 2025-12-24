#!/bin/sh

# This script is the entrypoint for the container.
# It prepares the Laravel application and starts all services via supervisor.

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Starting Laravel application on Fly.io..."

# Wait for database to be ready (if DATABASE_URL is set)
if [ -n "$DATABASE_URL" ]; then
    echo "Waiting for database connection..."
    sleep 5
fi

# Run Laravel optimizations (skip if migrations haven't run yet)
echo "Caching Laravel configuration..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

# Create storage link if not exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage symlink..."
    php /var/www/html/artisan storage:link || true
fi

# Publish Filament assets
echo "Publishing Filament assets..."
php /var/www/html/artisan filament:assets || true

# Set correct permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

echo "Starting services via supervisor..."
# Start supervisor which will manage PHP-FPM, Nginx, and Queue Worker
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/laravel.conf
