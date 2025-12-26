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

# Configure Nginx for large file uploads
echo "Configuring Nginx for large file uploads..."
cat > /etc/nginx/sites-enabled/default << 'EOF'
server {
    listen 0.0.0.0:8080;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # Allow uploads up to 100MB
    client_max_body_size 100M;
    client_body_timeout 300;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_buffering off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

echo "Starting services via supervisor..."
# Create main supervisord config that includes our Laravel config
cat > /etc/supervisor/supervisord.conf << 'SUPERVISORD_EOF'
[unix_http_server]
file=/var/run/supervisor.sock
chmod=0700

[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
pidfile=/var/run/supervisord.pid

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[include]
files = /etc/supervisor/conf.d/*.conf
SUPERVISORD_EOF

# Start supervisor which will manage PHP-FPM, Nginx, and Queue Worker
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
