# Модель данных

Статус: проектирование. СУБД: MySQL 8.4 по текущему README репозитория; таблицы InnoDB, строки `utf8mb4`, время UTC. Типы указаны в терминах MySQL. Существующие `users` и таблицы Laravel нужно сверить с миграциями до написания миграций игры.

## ER-связи

```text
categories 1 ── * levels 1 ── * level_translations
                         └── 1 ── 4 level_images
users 1 ── 1 user_wallets 1 ── * wallet_transactions
users 1 ── * user_level_progress * ── 1 levels
users 1 ── * idempotency_requests
```

Daily challenges, achievements, leaderboard snapshots, purchases и ad events не входят в MVP и добавляются в своих фазах.

## Таблицы MVP

### `users` (существующая таблица Laravel, адаптировать после аудита)

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `name` | VARCHAR(120) | nullable для гостя |
| `email` | VARCHAR(255) | nullable, unique (уточнить существующую схему) |
| `password` | VARCHAR(255) | nullable для гостя |
| `preferred_locale` | VARCHAR(16) | NOT NULL, default `ru` |
| `is_guest` | BOOLEAN | NOT NULL, default true |
| `created_at`, `updated_at` | TIMESTAMP | стандарт Laravel |

Гостевая авторизация: случайный высокоэнтропийный bearer token хранится в стандартной таблице токенов Laravel Sanctum; в клиенте токен хранится в защищённом хранилище ОС. Не хранить сырой токен. Sanctum сейчас отсутствует в `composer.json`; добавить его как отдельную согласованную задачу перед реализацией API авторизации.

### `categories`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `slug` | VARCHAR(100) | NOT NULL, UNIQUE |
| `sort_order` | INT UNSIGNED | NOT NULL, default 0 |
| `is_active` | BOOLEAN | NOT NULL, default true |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

### `category_translations`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `category_id` | BIGINT UNSIGNED | FK → categories.id, cascade delete |
| `locale` | VARCHAR(16) | NOT NULL |
| `name` | VARCHAR(120) | NOT NULL |

Уникальность `(category_id, locale)`, индекс `(locale, name)`. Отдельная таблица нужна для локализованных категорий.

### `levels`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `category_id` | BIGINT UNSIGNED | FK → categories.id, restrict delete |
| `sequence` | INT UNSIGNED | NOT NULL |
| `difficulty` | TINYINT UNSIGNED | NOT NULL, диапазон 1–5 валидируется приложением |
| `status` | VARCHAR(16) | NOT NULL, default `draft`; `published`, `disabled`, `archived` |
| `published_at` | TIMESTAMP | nullable |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Индексы `(status, sequence, id)` для выдачи, `(category_id, status, sequence)`. `sequence` не unique: допускает сортировку/перестановку через временные значения. API упорядочивает по `(sequence,id)`.

### `level_translations`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, cascade delete только для черновика |
| `locale` | VARCHAR(16) | NOT NULL |
| `answer_display` | VARCHAR(100) | NOT NULL, Unicode UTF-8 |
| `answer_normalized` | VARCHAR(200) | NOT NULL, Unicode NFC/casefold |
| `letter_tiles` | JSON | NOT NULL, массив строк-графем; правильные + лишние плитки |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(level_id, locale)`. Индекс `level_id`. JSON нужен для локализованного набора графем переменной длины; его валидация производится приложением. Ответ не отдаётся игровому endpoint.

### `level_images`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, restrict delete после публикации |
| `position` | TINYINT UNSIGNED | NOT NULL, 1–4 |
| `storage_disk` | VARCHAR(64) | NOT NULL |
| `storage_key` | VARCHAR(512) | NOT NULL |
| `mime_type` | VARCHAR(100) | NOT NULL |
| `width`, `height` | INT UNSIGNED | nullable до обработки |
| `variants` | JSON | nullable, ключи размеров/форматов |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(level_id, position)`, индекс `level_id`. Проверка ровно четырёх позиций — при публикации, так как SQL CHECK не выражает число дочерних строк.

### `user_level_progress`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `user_id` | BIGINT UNSIGNED | FK → users.id, cascade delete |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, restrict delete |
| `status` | VARCHAR(16) | NOT NULL, default `in_progress` |
| `attempt_count` | INT UNSIGNED | NOT NULL, default 0 |
| `hints_used` | TINYINT UNSIGNED | NOT NULL, default 0 |
| `started_at` | TIMESTAMP | NOT NULL |
| `completed_at` | TIMESTAMP | nullable |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(user_id, level_id)` защищает от дублирования прогресса. Индекс `(user_id, status, level_id)` для продолжения игры и `(level_id, status)` для статистики.

### `user_wallets`

| Поле | Тип | Ограничения |
|---|---|---|
| `user_id` | BIGINT UNSIGNED | PK и FK → users.id, cascade delete |
| `balance` | BIGINT UNSIGNED | NOT NULL, default 0 |
| `updated_at` | TIMESTAMP | NOT NULL |

Баланс — кэш суммы журнала, обновляемый только серверной транзакцией; клиентское значение никогда не принимается.

### `wallet_transactions`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `user_id` | BIGINT UNSIGNED | FK → users.id, restrict delete |
| `amount` | BIGINT | NOT NULL, знак плюс/минус |
| `balance_after` | BIGINT UNSIGNED | NOT NULL |
| `reason` | VARCHAR(32) | NOT NULL: `level_reward`, `hint_cost`, `admin_adjustment` |
| `reference_type` | VARCHAR(32) | NOT NULL |
| `reference_id` | BIGINT UNSIGNED | nullable |
| `idempotency_key` | VARCHAR(80) | nullable |
| `created_at` | TIMESTAMP | NOT NULL |

Уникальность `(user_id, reason, reference_type, reference_id)` для одноразовой награды/списания по игровой сущности; уникальность `(user_id, idempotency_key)` для клиентских операций (NULL допускается для серверных записей). Индекс `(user_id, created_at)`.

### `idempotency_requests`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `user_id` | BIGINT UNSIGNED | FK → users.id, cascade delete |
| `key` | VARCHAR(80) | NOT NULL |
| `operation` | VARCHAR(80) | NOT NULL |
| `request_hash` | CHAR(64) | NOT NULL |
| `response_status` | SMALLINT UNSIGNED | nullable до завершения |
| `response_body` | JSON | nullable до завершения |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(user_id, key)`, индекс `created_at` для очистки старых ключей. Повтор ключа с иным hash получает 409. Запись и игровая операция атомарны.

## Ограничения и миграция

- Все FK имеют явный тип, согласованный с реальной `users.id`.
- Мягкое удаление уровня реализуется статусом `archived`, не `deleted_at`, чтобы сохранять ссылки прогресса.
- Не создаём отдельные таблицы `hints`/`user_coins`: использование подсказок находится в прогрессе, движение валюты — в ledger.
- До миграций проверяются текущие users/permissions/jobs/cache migrations и СУБД конфигурация. Не менять уже созданные пользователем миграции без необходимости.
