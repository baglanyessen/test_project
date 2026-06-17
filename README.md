# Order Processing Service

Сервис обработки заказов. Laravel + PostgreSQL + Docker.

## Запуск

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

API доступен на `http://localhost:8000/api/orders`
