# Модель данных

Статус: категории TASK-021, уровни TASK-022, переводы TASK-023, изображения TASK-024 и схема/модель `players` TASK-020 реализованы. MySQL миграция `players` ожидает применения в локальном Docker-окружении; тесты мигрируют схему в SQLite. Прогресс, кошелёк и идемпотентность ниже — целевая модель. СУБД: MySQL 8.4 по README; игровые строки используют `utf8mb4`, время UTC.

## ER-связи

```text
categories 1 ── * levels 1 ── * level_translations
                         └── 1 ── 4 level_images
users 0..1 ── 0..1 players 1 ── 1 player_wallets 1 ── * wallet_transactions
players 1 ── * player_level_progress * ── 1 levels
players 1 ── * idempotency_requests
users/players 1 ── * personal_access_tokens (polymorphic tokenable)
```

Daily challenges, achievements, leaderboard snapshots, purchases и ad events не входят в MVP и добавляются в своих фазах.

## Таблицы MVP

### Аудит существующего `users` (TASK-010 завершена)

Имеющаяся миграция создаёт `users` с обязательными `email` и `password`, nullable `name`/`surname`, email verification и remember token. Таблица в работающей MySQL совпадает с миграцией: 9 колонок, InnoDB, `utf8mb4_unicode_ci`, уникальный индекс email; поля `preferred_locale` и `is_guest` отсутствуют. Миграции Sanctum, каталога, переводов и изображений применены; новая миграция `players` не изменяет `users`.

Модель `User` массово разрешает дополнительные поля (`block`, `avatar`, `nickname`, `country`, `timezone` и другие), которых нет в фактической таблице. Нужно проверить использование этих полей при изменении соответствующих функций. `users` остаётся сущностью web-учётной записи; игровые данные и гостевой доступ принадлежат отдельному `Player`.

### `users` (существующая таблица Laravel)

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `name` | VARCHAR(255) | nullable, web-учётная запись |
| `surname` | VARCHAR(255) | nullable, web-учётная запись |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE; не ослаблять для гостевой игры |
| `password` | VARCHAR(255) | NOT NULL; не создавать фиктивные значения для гостя |
| `created_at`, `updated_at` | TIMESTAMP | стандарт Laravel |

### `players` (TASK-020)

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `user_id` | BIGINT UNSIGNED | nullable, UNIQUE, FK → users.id, `ON DELETE SET NULL` |
| `locale` | VARCHAR(16) | NOT NULL, default `ru`; значение из `game.supported_locales` |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Каждая игровая сущность может существовать без регистрации. При привязке к аккаунту обновляется `players.user_id` у существующей строки: `players.id` не меняется, поэтому прогресс, кошелёк и история сохраняются. Уникальный nullable `user_id` задаёт максимум одного игрового профиля на аккаунт. Не использовать физический device ID как идентичность и не создавать гостю строку в `users`.

Laravel Sanctum использует полиморфный `personal_access_tokens.tokenable_type/tokenable_id`. Для мобильной игры токен выпускает аутентифицируемая модель `Player` с `HasApiTokens`; web session и существующая модель `User` остаются отдельными. Игровые маршруты должны принимать только principal типа `Player`. Сырой bearer token возвращается один раз и хранится на устройстве в защищённом хранилище ОС.

Runtime PHP в local/stage/prod образах включает `intl` и `mbstring`; `composer.json` требует `ext-intl`. Проверено в локальном PHP 8.4 контейнере: класс `Normalizer` доступен.

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
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(category_id, locale)`, индекс `(locale, name)`. Отдельная таблица нужна для локализованных категорий.

### `levels`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `category_id` | BIGINT UNSIGNED | FK → categories.id, restrict delete |
| `sequence` | INT UNSIGNED | NOT NULL |
| `difficulty` | TINYINT UNSIGNED | NOT NULL, диапазон 1–5 валидируется приложением |
| `status` | VARCHAR(16) | NOT NULL, default `draft`; `published`, `disabled`, `archived`; Eloquent enum cast ограничивает состояния в модели |
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

Уникальность `(level_id, locale)` одновременно поддерживает поиск переводов по `level_id`. JSON нужен для локализованного набора графем переменной длины; его валидация производится приложением. `answer_normalized` рассчитывается серверным нормализатором и никогда не отдаётся игровому endpoint.

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

Уникальность `(level_id, position)` одновременно поддерживает выборку изображений уровня. `LevelImageSetValidator` проверяет позиции ровно `[1, 2, 3, 4]` перед публикацией; одна только уникальность не гарантирует, что нет пропусков.

### `player_level_progress`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | FK → players.id, cascade delete |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, restrict delete |
| `status` | VARCHAR(16) | NOT NULL, default `in_progress` |
| `attempt_count` | INT UNSIGNED | NOT NULL, default 0 |
| `hints_used` | TINYINT UNSIGNED | NOT NULL, default 0 |
| `started_at` | TIMESTAMP | NOT NULL |
| `completed_at` | TIMESTAMP | nullable |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, level_id)` защищает от дублирования прогресса. Индекс `(player_id, status, level_id)` для продолжения игры и `(level_id, status)` для статистики.

### `player_wallets`

| Поле | Тип | Ограничения |
|---|---|---|
| `player_id` | BIGINT UNSIGNED | PK и FK → players.id, cascade delete |
| `balance` | BIGINT UNSIGNED | NOT NULL, default 0 |
| `updated_at` | TIMESTAMP | NOT NULL |

Баланс — кэш суммы журнала, обновляемый только серверной транзакцией; клиентское значение никогда не принимается.

### `wallet_transactions`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | FK → players.id, restrict delete |
| `amount` | BIGINT | NOT NULL, знак плюс/минус |
| `balance_after` | BIGINT UNSIGNED | NOT NULL |
| `reason` | VARCHAR(32) | NOT NULL: `level_reward`, `hint_cost`, `admin_adjustment` |
| `reference_type` | VARCHAR(32) | NOT NULL |
| `reference_id` | BIGINT UNSIGNED | nullable |
| `idempotency_key` | VARCHAR(80) | nullable |
| `created_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, reason, reference_type, reference_id)` для одноразовой награды/списания по игровой сущности; уникальность `(player_id, idempotency_key)` для клиентских операций (NULL допускается для серверных записей). Индекс `(player_id, created_at)`.

### `idempotency_requests`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | FK → players.id, cascade delete |
| `key` | VARCHAR(80) | NOT NULL |
| `operation` | VARCHAR(80) | NOT NULL |
| `request_hash` | CHAR(64) | NOT NULL |
| `response_status` | SMALLINT UNSIGNED | nullable до завершения |
| `response_body` | JSON | nullable до завершения |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, key)`, индекс `created_at` для очистки старых ключей. Повтор ключа с иным hash получает 409. Запись и игровая операция атомарны.

## Ограничения и миграция

- FK игровых данных ссылаются на `players.id`; аккаунтная связь `players.user_id` ссылается на существующий `users.id`.
- Мягкое удаление уровня реализуется статусом `archived`, не `deleted_at`, чтобы сохранять ссылки прогресса.
- Не создаём отдельные таблицы `hints`/`user_coins`: использование подсказок находится в прогрессе, движение валюты — в ledger.
- До миграций проверяются текущие users/permissions/jobs/cache migrations и СУБД конфигурация. Не менять уже созданные пользователем миграции без необходимости.
