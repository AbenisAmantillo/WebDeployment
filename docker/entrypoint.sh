#!/bin/sh
set -e

if [ -z "${DATABASE_URL}" ]; then
    echo "DATABASE_URL is not set."
    exit 1
fi

echo "Configuring Firebase credentials..."
firebase_credentials_path="$(php bin/firebase-credentials-path.php)"
export FIREBASE_CREDENTIALS="${firebase_credentials_path}"

echo "Waiting for database..."
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
    sleep 2
done
echo "Database is ready."

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT key pair..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "${APP_ENV}" = "prod" ]; then
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod
fi

exec "$@"
