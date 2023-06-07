#!/usr/bin/env bash

cd /app
composer require symfony/runtime
ln -s /usr/bin/symfony /bin/symfony

composer clearcache
composer update --prefer-source --no-dev --optimize-autoloader
bin/console clear-fs-adapter-cache

mkdir /app/public/img
chmod -R 777 /app

php bin/console secrets:generate-keys
php bin/console cache:clear --env=prod
