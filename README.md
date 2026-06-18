# Order Processing Service

Тестовое задание: Асинхронный сервис обработки заказов. 
Стек: Laravel 11, PHP 8.3, PostgreSQL, Redis, Docker.

## Как запустить

1. Клонируем:
   ```bash
   git clone https://github.com/baglanyessen/test_project.git
   cd test_project
   ```
2. Настраиваем окружение:
   ```bash
   cp .env.example .env
   ```
3. Поднимаем контейнеры:
   ```bash
   docker compose up -d --build
   ```
4. Устанавливаем зависимости и ключи:
   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   ```
5. Накатываем БД и тестовые товары (ID: 1, 2, 3):
   ```bash
   docker compose exec app php artisan migrate:fresh --seed
   ```

## Как это работает

При создании заказа API моментально отвечает `202 Accepted` (статус `pending`), чтобы клиент не ждал. Основная логика уходит в фоновую очередь Redis (`ProcessOrderJob`).

Что делает воркер:
1. Проверяет статусы (идемпотентность — защита от двойного списания).
2. Лочит нужные товары в БД через `lockForUpdate()`. Это спасает от гонки данных (race conditions) при одновременных заказах.
3. Списывает остатки, считает итоговую сумму по актуальным ценам и ставит статус `confirmed`. При нехватке товара переводит в `failed`.
4. Если Job упал окончательно после 3 попыток (backoff: 1, 5, 10 сек), заказ помечается как `failed` через метод `failed()`.

## API

### 1. Создать заказ
**POST** `/orders`

```bash
curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "customer_name": "Иван",
    "customer_email": "ivan@example.com",
    "items": [
      { "product_id": 1, "quantity": 2 },
      { "product_id": 3, "quantity": 1 }
    ]
  }'
```

Ответ (`202 Accepted`):
```json
{
  "message": "Order created and is pending processing.",
  "order_id": 1,
  "status": "pending"
}
```

### 2. Проверить статус заказа
**GET** `/orders/{id}`

```bash
curl http://localhost:8000/orders/1 \
  -H "Accept: application/json"
```

Ответ после обработки воркером:
```json
{
  "id": 1,
  "status": "confirmed",
  "total_amount": "2079.97",
  "fail_reason": null,
  "items": [
    {
      "product_id": 1,
      "product_name": "Laptop",
      "quantity": 2,
      "price": "999.99"
    },
    {
      "product_id": 3,
      "product_name": "Headphones",
      "quantity": 1,
      "price": "79.99"
    }
  ]
}
```

### 3. Пример с ошибкой (недостаточно товара)

```bash
curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "customer_name": "Петр",
    "items": [
      { "product_id": 2, "quantity": 9999 }
    ]
  }'
```

После обработки статус будет `failed` с описанием причины в `fail_reason`.

## Тесты

```bash
docker compose exec app php artisan test
```

Покрытие: бизнес-логика OrderService (успех + нехватка товара), API эндпоинты и эмуляция конкурентных запросов для проверки пессимистичных блокировок.

## Решения и компромиссы

- **Predis вместо phpredis**: Не нужно компилировать C-расширение в Docker, проще в настройке. Для тестового задания разница в производительности не критична.
- **Пессимистичные блокировки (`lockForUpdate`)**: Выбраны вместо оптимистичных, потому что проще в реализации и надёжнее при высокой конкуренции за один товар.
- **Обработка заказа в Job**: Заказ сначала создаётся со статусом `pending`, потом обрабатывается в фоне. Клиент не ждёт — сразу получает `202`. Если воркер упадёт, джоба автоматически повторится (3 попытки с backoff 1/5/10 сек). При окончательном провале — `failed()` ставит заказу статус `failed`.
- **Цены фиксируются при обработке**: Цена берётся из каталога в момент обработки, а не при создании заказа. Так актуальнее.

## Что бы доработал при большем времени

- Pagination для списка заказов
- API Resource классы для унификации формата ответов
- Событийная модель (Events/Listeners) для уведомлений при смене статуса
- Кэширование каталога товаров в Redis
- Rate limiting на эндпоинты
- Swagger/OpenAPI документация
