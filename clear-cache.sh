#!/bin/bash

# Clear all Laravel caches
echo "Clearing Laravel caches..."

php artisan clear-compiled
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "All caches cleared successfully!"
