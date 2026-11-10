# orders-saga

[![tests](https://github.com/tlegen99/orders-saga/actions/workflows/tests.yml/badge.svg)](https://github.com/tlegen99/orders-saga/actions/workflows/tests.yml)

Учебный сервис заказов и оплаты на Laravel. Показывает, как сделать надёжный процесс «заказ → оплата → подтверждение» в системе, где части общаются через брокер сообщений и любая из них может упасть в любой момент.

Главная идея: **гарантии держит база данных** (транзакции и уникальные индексы), без проверки `if exists` в коде.

**Стек:** PHP 8.5, Laravel 13, PostgreSQL 18, NATS JetStream, Pest, Docker (Laravel Sail).

## Что умеет

- `POST /api/orders` создаёт заказ и резервирует товар на складе.
- Повторный запрос с тем же `Idempotency-Key` не создаёт второй заказ, а возвращает сохранённый ответ.
- Сообщение об оплате не теряется, даже если брокер недоступен (transactional outbox).
- Повторно доставленное сообщение не списывает деньги дважды.
- Если оплата не прошла, товар возвращается на склад, а заказ отменяется (сага с компенсацией).
- Если ответ от Payments не пришёл за 5 минут, заказ отменяется по таймауту, а товар возвращается.
- Если оплата всё-таки прошла после отмены, заказ не «воскресает», а отправляется запрос на возврат денег.

## Как проходит заказ

```mermaid
%%{init: {'sequence': {'mirrorActors': false}}}%%
sequenceDiagram
    participant C as Клиент
    participant O as Orders (API)
    participant DB as PostgreSQL
    participant P as outbox:publish
    participant N as NATS JetStream
    participant Pay as payments:consume
    participant OC as orders:consume

    C->>O: POST /api/orders + Idempotency-Key
    O->>DB: одна транзакция: ключ + резерв товара + заказ + письмо ChargePayment в outbox
    O-->>C: 201, статус payment_pending
    P->>DB: берёт неотправленные письма (FOR UPDATE SKIP LOCKED)
    P->>N: payments.charge, ждёт подтверждения (PubAck)
    N->>Pay: ChargePayment
    Pay->>DB: одна транзакция: отметка в processed_messages + оплата + письмо-ответ в outbox
    Pay-->>N: ack
    P->>N: orders.payment_succeeded / orders.payment_failed
    N->>OC: ответ от Payments
    alt оплата прошла
        OC->>DB: заказ → confirmed
    else отказ
        OC->>DB: компенсация: вернуть товар на склад, заказ → cancelled
    end
    OC-->>N: ack
```

Статусы заказа: `payment_pending` → `confirmed` или `cancelled`. Оба конечные: из них перейти никуда нельзя, это проверяет `OrderStatus::canTransitionTo()`.

Отдельно от сообщений раз в минуту работает `saga:timeouts`: заказы, которые висят в `payment_pending` дольше таймаута, отменяются той же компенсацией, что и при отказе оплаты.

## Ключевые решения

| Проблема | Решение | Где в коде |
|---|---|---|
| Ответ потерялся в сети, клиент повторил запрос, и получилось два заказа | `Idempotency-Key`: ключ, заказ и сохранённый ответ пишутся в **одной транзакции**. Второй запрос с тем же ключом ждёт на уникальном индексе, потом отдаёт готовый ответ с заголовком `Idempotent-Replayed: true`. Тот же ключ с другим телом → 422 | `app/Http/Middleware/EnsureIdempotency.php`, `app/Services/RequestHasher.php` |
| Два покупателя одновременно берут последний товар | `SELECT ... FOR UPDATE` в транзакции; товары блокируются в порядке `id`, чтобы не было deadlock; `CHECK (stock >= 0)` в базе как последний рубеж | `app/Services/OrderService.php` |
| Заказ сохранён, а сообщение в брокер не ушло (dual write) | Transactional outbox: сообщение пишется в таблицу в той же транзакции, что и заказ. Отдельная команда отправляет его с повторами | `app/Services/Outbox.php`, `app/Console/Commands/PublishOutbox.php` |
| Отправка «отправил и забыл» может молча потерять сообщение | Публикация через `request()` с ожиданием подтверждения от JetStream (PubAck). Нет подтверждения → письмо остаётся в outbox, растёт `attempts` | `app/Messaging/NatsMessageBus.php` |
| Сообщение доставлено дважды (повтор почтальона или брокера) | `Nats-Msg-Id` отсекает быстрые повторы в брокере. Консьюмер первым шагом вставляет `(consumer, message_id)` в `processed_messages` в одной транзакции с бизнес-логикой; `ack` - после коммита | `app/Payments/ChargePaymentHandler.php`, `app/Orders/OrderSagaHandler.php` |
| Оплата не прошла, а товар уже зарезервирован | Компенсация: отдельное действие `stock = stock + quantity`, заказ → `cancelled` с причиной | `app/Orders/OrderSagaHandler.php` |
| Payments не отвечает, заказ и товар зависли навсегда | Таймаут: раз в минуту ищем заказы в `payment_pending` старше 5 минут (индекс `(status, updated_at)`). Каждый отменяется в своей короткой транзакции: блокировка → **перепроверка статуса** → компенсация. Перепроверка нужна, потому что ответ от Payments мог прийти между поиском и блокировкой | `app/Console/Commands/SagaTimeouts.php`, `app/Orders/OrderCanceller.php` |
| Запоздалое сообщение пытается «воскресить» отменённый заказ | Проверка допустимости перехода статуса; недопустимый переход логируется и пропускается без исключения, чтобы сообщение не крутилось по кругу | `app/Enums/OrderStatus.php` |
| Таймаут отменил заказ, а Payments потом всё-таки списал деньги | Вторая компенсация: на запоздалый `PaymentSucceeded` для отменённого заказа Orders пишет в outbox команду `RefundPayment` | `app/Orders/OrderSagaHandler.php` |
| Служебные таблицы растут бесконечно | `MassPrunable` + `model:prune` раз в сутки: ключи идемпотентности после истечения, отправленные письма outbox через 7 дней. Неотправленные (`published_at IS NULL`) не удаляются никогда | `app/Models/IdempotencyKey.php`, `app/Models/OutboxMessage.php` |

Ещё по мелочи:

- Деньги хранятся в копейках (`bigint`), и API тоже отдаёт копейки: никаких float.
- В позиции заказа сохраняется снимок цены, а сумма считается на сервере по ценам из базы.
- Брокер спрятан за интерфейсом `MessageBus`: в тестах подменяется фейком, а NATS можно заменить на другой брокер одной строкой в `AppServiceProvider`.
- Слои: Form Request → readonly DTO → сервис → API Resource; контроллер только соединяет их.

## Запуск

Нужен Docker.

```bash
git clone https://github.com/tlegen99/orders-saga.git
cd orders-saga
cp .env.example .env

# установить зависимости без локального PHP
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed   # 5 товаров с известными ценами
./vendor/bin/sail artisan nats:setup        # stream SAGA и консьюмеры payments / orders
```

Дальше нужны четыре фоновых процесса, каждый в своём терминале:

```bash
./vendor/bin/sail artisan outbox:publish     # отправляет письма из outbox в NATS
./vendor/bin/sail artisan payments:consume   # Payments: списывает деньги
./vendor/bin/sail artisan orders:consume     # Orders: подтверждает заказ или запускает компенсацию
./vendor/bin/sail artisan schedule:work      # планировщик: saga:timeouts раз в минуту, model:prune раз в сутки
```

На сервере вместо `schedule:work` в cron добавляется `* * * * * php artisan schedule:run`.

## Проверка руками

Успешный заказ:

```bash
curl -s -X POST localhost/api/orders -H 'Content-Type: application/json' \
  -H "Idempotency-Key: demo-$(date +%s)" \
  -d '{"items":[{"product_id":1,"quantity":1}]}'
```

Ответ придёт со статусом `payment_pending`, а через пару секунд заказ станет `confirmed`.

Отказ оплаты: фейковый шлюз отклоняет суммы, которые заканчиваются на 13 рублей. Создайте товар за 1313 ₽ и закажите его:

```bash
./vendor/bin/sail artisan tinker --execute="dump(App\Models\Product::create(['name' => 'Test', 'price' => 131300, 'stock' => 10])->id);"
```

Заказ станет `cancelled`, а остаток товара вернётся к исходному.

Недоступность брокера: `sail stop nats`, создайте заказ. Письмо останется в `outbox_messages` с ошибкой в `last_error`. После `sail start nats` оно уйдёт.

Таймаут и запоздалая оплата - самый интересный сценарий:

1. В `.env` поставьте `SAGA_PAYMENT_TIMEOUT_MINUTES=1`. Запустите `outbox:publish` и `orders:consume`, а `payments:consume` **не запускайте**, будто Payments упал.
2. Создайте заказ: он `payment_pending`, товар зарезервирован, а `ChargePayment` ждёт в NATS.
3. Через минуту выполните `sail artisan saga:timeouts`: заказ `cancelled` с причиной `payment_timeout`, товар вернулся.
4. Запустите `payments:consume`. Он заберёт старое сообщение и спишет деньги, а `orders:consume` не подтвердит отменённый заказ, а запишет в outbox `RefundPayment`.

## Тесты

```bash
./vendor/bin/sail artisan test
```

Тесты идут в отдельную PostgreSQL-базу `testing`, поэтому блокировки, CHECK-ограничения и `jsonb` работают так же, как в приложении. Что проверяется:

- создание заказа, нехватка товара (резерв откатывается целиком), несуществующий товар, валидация;
- идемпотентность: повтор, другое тело, другой порядок ключей в JSON, отсутствие ключа;
- outbox: письмо пишется вместе с заказом и не появляется при откате; публикация; брокер недоступен → письмо не теряется;
- консьюмер Payments: успех, отказ шлюза, повторное сообщение не создаёт вторую оплату;
- сага: подтверждение, компенсация, повторный отказ возвращает товар только один раз, запоздалый успех не меняет отменённый заказ;
- таймауты: зависший заказ отменяется и возвращает товар, свежий и подтверждённый не трогаются, запоздалая оплата отменённого заказа создаёт `RefundPayment`;
- очистка: просроченные ключи идемпотентности удаляются, свежие остаются.

Тесты запускаются в GitHub Actions на каждый push, против PostgreSQL в service-контейнере.

Гонку двух одновременных запросов с одним ключом тестами не проверить: в тестах всё идёт в одном соединении с базой. Её проверял руками двумя параллельными `curl`: оба ответа с одним uuid, заказ один.

## Что осознанно упрощено

- Orders и Payments - модули одного приложения с общей базой; общаются только через NATS. Payments ради `order_id` читает таблицу `orders`; в отдельных сервисах у Payments была бы своя база.
- Платёжный шлюз фейковый. Uuid заказа уже передаётся в него как idempotency key, чтобы настоящий шлюз не списал деньги дважды при повторе.
- Сообщения обрабатываются по одному; служебные ответы JetStream без тела пропускаются. Сообщения, которые невозможно обработать, только логируются; на проде их стоит отправлять в отдельную очередь (DLQ).
- Статусы `pending` и `paid` пока не используются: это задел под будущие шаги, например доставку.
- У команды `RefundPayment` пока нет обработчика: она сохраняется в stream и ждёт консьюмера возвратов.
- Фоновые процессы запускаются вручную; на проде их держал бы supervisor или отдельные контейнеры.
- Vue-часть (`resources/js`, Inertia) - пока шаблон Laravel без изменений, задел под фронтенд со статусами заказов.

## Что дальше

- [ ] обработчик `RefundPayment` в Payments;
- [ ] лимит повторных доставок (`MaxDeliver`) и отдельная очередь для сообщений, которые невозможно обработать (DLQ);
- [ ] не блокировать outbox одним «отравленным» письмом: после N попыток пропускать его;
- [ ] очистка `processed_messages`;
- [ ] correlation id в логах и заголовках сообщений, чтобы проследить один заказ через все сервисы;
- [ ] `GET /api/orders` и фронт на Vue 3 + TypeScript + Pinia со статусами заказов.
