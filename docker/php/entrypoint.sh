#!/bin/sh
# Démarrage des conteneurs PHP de production (php-fpm, worker, scheduler).
set -e

# Attente de PostgreSQL (jusqu'à 60 s)
if [ -n "$DATABASE_URL" ]; then
  i=0
  until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
      echo "PostgreSQL injoignable après 60 s" >&2
      exit 1
    fi
    sleep 2
  done
fi

# Un seul conteneur (php-fpm) applique les migrations et crée les clés JWT si besoin.
if [ "${RADAR_ROLE:-web}" = "web" ]; then
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
  php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

php bin/console cache:warmup --no-interaction >/dev/null

exec "$@"
