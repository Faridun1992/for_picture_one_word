# Модель данных

Статус: категории TASK-021, уровни TASK-022, переводы TASK-023, изображения TASK-024, `players` TASK-020, прогресс TASK-025, кошелёк TASK-026 и идемпотентность TASK-027 реализованы. Миграции игровых таблиц проверены feature-тестами на SQLite; применение в локальную MySQL ожидает доступности Docker. СУБД: MySQL 8.4 по README; игровые строки используют `utf8mb4`, время UTC.

## ER-связи

```text
categories 1 ── * levels 1 ── * level_translations
                         └── 1 ── 4 level_images
users 0..1 ── 0..1 players 1 ── 1 player_wallets 1 ── * wallet_transactions
players 1 ── * player_level_progress * ── 1 levels
players 1 ── * idempotency_requests
users/players 1 ── * personal_access_tokens (polymorphic tokenable)
```

Daily challenges, achievements, leaderboard snapshots, purchases и ad events добавляются отдельными задачами. TASK-062 пока не создаёт таблицу аналитики: события передаются через интерфейс адаптера в ротируемый лог, а хранилище агрегатов для воронки будет определено в TASK-064.

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
| `sound_enabled` | BOOLEAN | NOT NULL, default true |
| `haptics_enabled` | BOOLEAN | NOT NULL, default true |
| `theme` | VARCHAR(8) | NOT NULL, default `system`; `system`, `light`, `dark` |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Каждая игровая сущность может существовать без регистрации. При привязке к аккаунту обновляется `players.user_id` у существующей строки: `players.id` не меняется, поэтому прогресс, кошелёк и история сохраняются. Уникальный nullable `user_id` задаёт максимум одного игрового профиля на аккаунт. Не использовать физический device ID как идентичность и не создавать гостю строку в `users`.

Laravel Sanctum использует полиморфный `personal_access_tokens.tokenable_type/tokenable_id`. Для мобильной игры токен выпускает аутентифицируемая модель `Player` с `HasApiTokens`; web session и существующая модель `User` остаются отдельными. Игровые маршруты должны принимать только principal типа `Player`. Сырой bearer token возвращается один раз и хранится на устройстве в защищённом хранилище ОС: iOS Keychain (`ThisDeviceOnly`), Android — AES-GCM ciphertext в SharedPreferences с ключом Android Keystore. В браузерном режиме токен существует только в памяти страницы, `localStorage` и `sessionStorage` не используются.

Runtime PHP в local/stage/prod образах включает `intl` и `mbstring`; `composer.json` требует `ext-intl`. Проверено в локальном PHP 8.4 контейнере: класс `Normalizer` доступен.

Мобильные TASK-057/058 хранят несекретные снимки профиля/прогресса, каталога и игрового контента, локальный черновик ответа и ожидающее действие с `Idempotency-Key` в IndexedDB устройства. Это не серверная БД и не источник истины для баланса или результата; bearer token туда не записывается. Изменения схемы API/БД для кэша и повтора не нужны.

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

Индексы `(status, sequence, id)` для выдачи, `(category_id, status, sequence)`. `sequence` не unique: API упорядочивает по `(sequence,id)`, а TASK-045 блокирует опубликованный набор, переносит его на временные значения и затем присваивает плотный порядок `1..N` в транзакции. Существующие дубликаты порядка разрешаются по ID.
Публикация и архивирование используют существующий `status` и не требуют миграции. Архивирование сохраняет `published_at`, изображения, переводы и ссылки истории прохождения.

### `level_translations`

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, cascade delete только для черновика |
| `locale` | VARCHAR(16) | NOT NULL |
| `answer_display` | VARCHAR(100) | NOT NULL, Unicode UTF-8; ограничение 1–12 grapheme clusters валидируется приложением |
| `answer_normalized` | VARCHAR(200) | NOT NULL, Unicode NFC/casefold |
| `letter_tiles` | JSON | NOT NULL, ровно 12 элементов для опубликованного перевода: ответные + отвлекающие графемы |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(level_id, locale)` одновременно поддерживает поиск переводов по `level_id`. JSON содержит ровно 12 отдельных плиток-графем; администратор задаёт distractors, а приложение валидирует их количество, Unicode-графемность и покрытие ответа с учётом повторов перед сохранением/публикацией. Ответ ограничен 12 графемами; для ответа из 12 графем дополнительные отвлекающие буквы не добавляются. `answer_normalized` рассчитывается серверным нормализатором и никогда не отдаётся игровому endpoint.

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
| `variants` | JSON | nullable, `thumbnail`/`display` с private storage key, исходным MIME и размерами |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(level_id, position)` одновременно поддерживает выборку изображений уровня. `LevelImageSetValidator` проверяет позиции ровно `[1, 2, 3, 4]` перед публикацией; одна только уникальность не гарантирует, что нет пропусков.
Оригиналы и варианты хранятся через Laravel Storage. Варианты генерируются асинхронно; их JSON-метаданные обновляются после успешной записи файлов. Изменение структуры таблицы не потребовалось.

### `player_level_progress` (TASK-025/033)

Admin-статистика TASK-046 агрегируется по `level_id` и текущим строкам прогресса; персональные атрибуты Player не извлекаются. Дополнительная схема для неё не требуется.

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | FK → players.id, cascade delete |
| `level_id` | BIGINT UNSIGNED | FK → levels.id, restrict delete |
| `status` | VARCHAR(16) | NOT NULL, default `in_progress` |
| `attempt_count` | INT UNSIGNED | NOT NULL, default 0 |
| `hints_used` | TINYINT UNSIGNED | NOT NULL, default 0 |
| `hint_state` | JSON | nullable; server-owned revealed answer positions and removed wrong tiles |
| `started_at` | TIMESTAMP | NOT NULL |
| `completed_at` | TIMESTAMP | nullable |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, level_id)` защищает от повторной записи прогресса для одного уровня. Индекс `(player_id, status, level_id)` поддерживает выбор незавершённого уровня игрока, `(level_id, status)` — агрегирование статистики по уровню. `status` принимает `in_progress` или `completed` и приводится к `PlayerLevelProgressStatus`; `hint_state` обновляет только серверная игровая логика. Удаление игрока удаляет его прогресс, удаление уровня с прогрессом запрещено.

### `player_wallets` (TASK-026)

| Поле | Тип | Ограничения |
|---|---|---|
| `player_id` | BIGINT UNSIGNED | PK и FK → players.id, cascade delete |
| `balance` | BIGINT UNSIGNED | NOT NULL, default 0 |
| `updated_at` | TIMESTAMP | NOT NULL |

Баланс — кэш суммы журнала, обновляемый только серверной транзакцией; клиентское значение никогда не принимается.

### `wallet_transactions` (TASK-026)

| Поле | Тип | Ограничения |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `player_id` | BIGINT UNSIGNED | FK → players.id, restrict delete |
| `amount` | BIGINT | NOT NULL, знак плюс/минус |
| `balance_after` | BIGINT UNSIGNED | NOT NULL |
| `reason` | VARCHAR(32) | NOT NULL: `level_reward`, `welcome_reward`, `milestone_reward`, `hint_cost`, `admin_adjustment` |
| `reference_type` | VARCHAR(32) | NOT NULL: `level`, `correct_answer`, `level_completion`, `level_milestone`, `player_welcome`, `level_hint` |
| `reference_id` | BIGINT UNSIGNED | nullable |
| `idempotency_key` | VARCHAR(80) | nullable |
| `created_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, reason, reference_type, reference_id)` для одноразовой награды/списания по игровой сущности; уникальность `(player_id, idempotency_key)` для клиентских операций (NULL допускается для серверных записей). Индекс `(player_id, created_at)`.

`WalletTransaction` — append-only модель: Eloquent update/delete запрещены; база ограничивает удаление игрока, пока остаётся его финансовая история. TASK-032/033 проводят welcome grant, rewards и списания только через серверный `WalletBalanceManager` в транзакции с row lock. Уникальность бизнес-ссылки обеспечивает одноразовые награды: ответ/завершение ссылаются на конкретный level разными `reference_type`, milestone — на порог, welcome grant — на Player, hint debit — на idempotency operation ID. Клиент не передаёт баланс, reward amount или hint price.

Начальная экономика хранится в `config/game.php`: старт +300 Coins; правильный ответ +10; завершение загадки +50; milestones 50/100/500/1000 решённых загадок +150/+300/+300/+500 одноразово; daily +100 за первое прохождение конкретной задачи; streak 5/10 +20/+50; rewarded ad +50, максимум 5 в сутки. Целевые цены подсказок после TASK-079: `reveal_letter` 60, `remove_wrong_letters` 40, `reveal_answer` 100 Coins; до изменения серверной конфигурации фактическая цена `reveal_letter` остаётся 30. Количество удаляемых неверных плиток — серверная настройка.

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
| `completed_at` | TIMESTAMP | nullable до финального результата |
| `created_at`, `updated_at` | TIMESTAMP | NOT NULL |

Уникальность `(player_id, key)` предотвращает повторное выполнение одного ключа для игрока. Индекс `(completed_at, id)` поддерживает пакетную очистку. Завершённый результат хранится 30 дней от `completed_at`; после срока ежедневная очистка может удалить или архивировать его. Незавершённые строки (`completed_at IS NULL`) исключены из TTL независимо от `created_at` и не удаляются этой очисткой. Повтор ключа в течение срока с теми же operation и canonical request hash возвращает сохранённые HTTP status/body; другое содержимое получает 409 `idempotency_key_reused`. Запись ключа, бизнес-изменения и финальный ответ фиксируются атомарно.

## Ограничения и миграция

- FK игровых данных ссылаются на `players.id`; аккаунтная связь `players.user_id` ссылается на существующий `users.id`.
- Мягкое удаление уровня реализуется статусом `archived`, не `deleted_at`, чтобы сохранять ссылки прогресса.
- Не создаём отдельные таблицы `hints`/`user_coins`: использование подсказок находится в прогрессе, движение валюты — в ledger.
- До миграций проверяются текущие users/permissions/jobs/cache migrations и СУБД конфигурация. Не менять уже созданные пользователем миграции без необходимости.
