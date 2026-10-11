#!/bin/bash
set -e
# Railway fournit le port d'écoute via $PORT.
if [ -n "$PORT" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf
fi
# Volume des médias : doit être inscriptible par Apache.
mkdir -p /var/www/html/wp-content/uploads
chown -R www-data:www-data /var/www/html/wp-content/uploads
# Installation automatique en arrière-plan (idempotente), pendant que l'entrypoint officiel prépare WordPress.
( /usr/local/bin/install.sh >> /proc/1/fd/1 2>&1 ) &
exec docker-entrypoint.sh "$@"
