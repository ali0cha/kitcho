#!/bin/sh
set -e
# Applique le schéma (idempotent) avant de lancer Apache : aucune étape d'installation manuelle.
if [ -n "$DB_HOST" ]; then
    php /var/www/html/bin/migrate.php
fi
exec docker-php-entrypoint "$@"
