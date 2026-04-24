#!/bin/sh
set -e

# Create storage symlink if missing (safe to re-run)
php artisan storage:link --force 2>/dev/null || true

# Fix storage permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

exec php-fpm
