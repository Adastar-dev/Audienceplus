#!/bin/sh
set -e

# Installe les dépendances PHP si le dossier vendor/ n'existe pas encore
# (premier démarrage, ou après un `docker compose down -v`).
if [ ! -f "vendor/autoload.php" ]; then
  echo "Installation des dépendances composer..."
  composer install --no-interaction --prefer-dist
fi

# Crée le .env à partir de .env.example s'il n'existe pas encore
if [ ! -f ".env" ]; then
  cp .env.example .env
fi

# Attend que MySQL soit prêt avant toute commande artisan
echo "Attente de la base de données..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  sleep 1
done
echo "Base de données prête."

php artisan key:generate --force
php artisan migrate --force

exec "$@"
