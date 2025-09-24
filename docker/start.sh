#!/bin/sh

echo "Aguardando PostgreSQL..."
while ! nc -z postgres 5432; do
  sleep 1
done

echo "PostgreSQL está pronto!"

# Copiar .env se não existir
if [ ! -f .env ]; then
    cp .env.example .env
    echo "Arquivo .env criado"
fi

# Gerar chave da aplicação se não existir
if ! grep -q "APP_KEY=" .env || [ -z "$(grep APP_KEY= .env | cut -d'=' -f2)" ]; then
    php artisan key:generate
    echo "Chave da aplicação gerada"
fi

# Executar migrações
php artisan migrate --force

# Iniciar servidor
php artisan serve --host=0.0.0.0 --port=8000