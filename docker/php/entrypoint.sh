#!/usr/bin/env sh
set -e

APP_ENV_VALUE="${APP_ENV:-prod}"

# Ensure writable dirs for cache/sessions/logs and uploads
mkdir -p "var/cache/${APP_ENV_VALUE}" "var/sessions/${APP_ENV_VALUE}" var/log public/uploads || true

# On some bind-mount setups (e.g. Windows), chown may fail; ignore errors.
chown -R www-data:www-data var public/uploads 2>/dev/null || true
chmod -R ug+rwX var public/uploads 2>/dev/null || true

exec docker-php-entrypoint php-fpm
