#!/usr/bin/env bash
# Run on the production server from the app directory, as the deploy user.
# Never seeds, never runs migrate:fresh/reset. Applies reviewed forward migrations only.
set -euo pipefail

git fetch origin main
git merge --ff-only origin/main

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Needs the passwordless sudo rules in docs/ci-cd.md
sudo -n chown -R www-data:www-data storage bootstrap/cache
sudo -n systemctl reload php8.4-fpm
