# REST API v1

Статус: health, guest session/revoke и catalog read endpoints реализованы; остальные endpoint ниже остаются контрактом реализации. Базовый URL `/api/v1`. JSON UTF-8. Время ISO 8601 UTC. Ответы ошибок единообразны: `{"message":"...","code":"...","errors":{}}`. Токен — `Authorization: Bearer …`. Игровой bearer token принадлежит `Player`; все игровые маршруты ограничиваются по IP и player ID. Существующая web-аутентификация `User` не даёт доступ к игровым маршрутам.

## Общие правила

- `Accept-Language: ru`, `tj` или `en`; неизвестный язык даёт 422, отсутствующий перевод — fallback на `en` только если это явно задано каталогом, иначе 404/422.
- `Idempotency-Key` обязателен для `POST` завершения уровня и подсказки; 8–80 ASCII символов. Завершённый status/body хранится 30 дней с `completed_at`; повтор в течение срока с теми же операцией и телом возвращает сохранённый результат без повторного выполнения. Ключ с другой операцией/телом получает 409 `idempotency_key_reused`; незавершённая операция получает 409 `idempotency_request_in_progress` и не удаляется по TTL. Это позволяет клиенту безопасно повторить offline-запрос после сетевого сбоя.
- API не принимает `coins`, `is_correct`, `completed`, `next_level_id` или цену подсказки от клиента.
- Пагинация cursor-based. Только опубликованные уровни доступны игроку. Guest session ограничена 10 запросами в минуту на IP. Защищённые игровые маршруты требуют bearer principal типа `Player` и ограничены 120 запросами в минуту на игрока и 600 на IP; превышение возвращает 429 `too_many_requests`. Админ API защищён web-сессией и authorization policy; ниже перечисленные `/admin` маршруты не используют мобильный токен.

## Игрок

### `GET /health`

Полный URL: `GET /api/v1/health`. Auth: нет. Response 200: `{"status":"ok"}` для проверки доступности маршрутизации. Не проверяет БД/Redis и не раскрывает внутренние сведения. Все ошибки `/api/*` возвращают `{ "message": "...", "code": "...", "errors": {} }`; при 422 поле `errors` содержит массивы сообщений по именам полей. 5xx не раскрывают внутренний текст исключения. Основные коды: `validation_failed` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `conflict` (409), `too_many_requests` (429), `internal_server_error` (5xx). Остальные web-маршруты сохраняют стандартное поведение Laravel.

### `POST /auth/guest`

Реализовано в TASK-030. Auth: нет. Создаёт `Player` и кошелёк без строки в `users`, выпускает Sanctum bearer token для `Player`. Request: `{"locale":"tj","device_name":"Phone"}`; оба поля optional, locale defaults to `ru`, `device_name` max 100 и используется только как имя токена, не как identity. Response 201: `{"data":{"token":"…","player":{"id":123,"locale":"tj"}}}`. Ошибки 422 invalid locale/device name, 429 после 10 запросов в минуту с одного IP. Каждый запрос создаёт отдельного гостевого игрока; клиент сохраняет токен в защищённом хранилище ОС.

### `DELETE /auth/session`

Реализовано в TASK-030. Auth: bearer игрока. Request отсутствует. Response 204 удаляет только текущий Sanctum token; другие сессии игрока остаются активными. 401 без/с невалидным токеном, 403 для токена `User`.

### `GET /me`

Auth: bearer игрока. Principal берётся из текущего Sanctum token (`auth()->user()`), игнорируя переданный клиентом `player_id`; response 200: player ID, locale, server wallet balance, настройки, `current_level_id`. Не включает сведения или секреты `User`. 401 без/с невалидным токеном; 403 при principal не типа `Player`.

### `PATCH /me/settings`

Auth: bearer. Request: `{"locale":"ru","sound_enabled":true,"haptics_enabled":true,"theme":"system"}`; необязательные поля. Response 200: сохранённые настройки. Валидация enum/boolean, 422; 401.

### `GET /categories`

Реализовано в TASK-031. Auth: bearer игрока. Query optional `locale` (default player locale), `cursor`, `limit` (1–50, default 20). Response 200: `data` содержит только активные категории с переводом для запрошенной локали, `id`, `slug`, `name`, числом опубликованных доступных уровней; `meta.next_cursor` и `meta.previous_cursor` — encoded cursor или null. Level count исключает уровни без перевода или ровно четырёх image positions. 401/422.

### `GET /levels`

Реализовано в TASK-031. Auth: bearer игрока. Query optional `locale` (default player locale), `category_id`, `after_id`, `cursor`, `limit` (1–50, default 20). Возвращает опубликованные уровни только при наличии category/level translation для языка и четырёх позиций изображений. `data` содержит id, порядок, сложность, локализованную категорию, `answer_length` в Unicode graphemes, `letter_tiles`, четыре упорядоченных image `{position,url,width,height}`, `locale` и `content_version`. Private storage выдаёт подписанный URL на 24 часа; public storage использует обычный URL. Ответы `answer_display` и `answer_normalized` исключены. Cursor находится в `meta`. 401, 404 для неизвестной категории, 422 для параметров, 429.

### `GET /levels/{level}`

Реализовано в TASK-031. Auth: bearer игрока. Response 200: карточка одного опубликованного и полного уровня; содержит `letter_tiles`, четыре объекта `{position,url,width,height}`, `answer_length`, locale, category translation и `content_version`. Private storage выдаёт подписанный URL на 24 часа; public storage использует обычный URL. `answer_display` и `answer_normalized` никогда не возвращаются. 404 если уровень не опубликован, неполон или недоступен для локали; 401/429.

### `POST /levels/{level}/attempts`

Auth: bearer; `Idempotency-Key` обязателен. Request: `{"answer":"ГУРБА"}` (UTF-8, max 100 graphemes). Сервер нормализует строку и сравнивает ответ. Response 200 неверно: `{"data":{"correct":false,"progress":{...}}}`; правильно: `{"data":{"correct":true,"completed_at":"…","reward":{"coins":10,"balance":40},"next_level_id":101}}`. Награда конфигурируется сервером и выдаётся лишь один раз. Ошибки: 401, 404, 409 для повторного завершения с несогласованным состоянием, 422 формат/длина, 429 throttle. Повтор ключа идемпотентен.

### `POST /levels/{level}/hints`

Auth: bearer; `Idempotency-Key` обязателен. Request: `{"type":"reveal_letter","position":2}` или `{"type":"remove_wrong_letters"}`. Стоимость серверная. Response 200: применённая подсказка, изменённое состояние плиток, новый баланс. 401, 404, 409 если уровень уже завершён, 422 неизвестный тип/позиция, 429, 422 `insufficient_coins` без изменений баланса. Транзакция и идемпотентность обязательны.

### `GET /progress`

Progress endpoint реализован в TASK-034; схема серверного хранения прогресса добавлена в TASK-025.

Auth: bearer игрока. Query optional `cursor`, `limit` 1–100 (default 50). Response 200: `data.current_level_id`, баланс, массив progress (`level_id`, sequence, status, attempts, hints, timestamps), статистика total/completed/attempts; `meta` содержит cursor. `current_level_id` — активный доступный уровень или первый доступный незавершённый, null если больше нет доступных уровней. Только текущий `Player`. 401/422.

`GET /me` и `PATCH /me/settings` реализованы в TASK-034. Новые игроки получают `sound_enabled=true`, `haptics_enabled=true`, `theme=system`; язык по умолчанию `ru`. Допустимые темы: `system`, `light`, `dark`.

### `GET /daily`

Auth: bearer. MVP: 200 с `{"data":{"available":false}}` до включения daily phase; после неё возвращает назначенную задачу и персональный статус. 401.

### `GET /leaderboard`

Не входит в MVP. Вводится вместе с рейтингом; контракт расширить при старте соответствующей задачи, не выдавать персональные данные пользователей.

## Администрирование (web)

Админ CRUD работает под web session + CSRF, с ролями/политиками и проверкой каждого действия. Доступы: `GET/POST /admin/levels`, `GET/PATCH/DELETE /admin/levels/{level}`, `POST /admin/levels/{level}/images`, `POST /admin/levels/{level}/publish`, `POST /admin/levels/reorder`, `GET /admin/statistics/levels`. Форма принимает категорию, сложность, статус, переводы, правильный ответ и плитки. Удаление опубликованного уровня архивирует. Загрузки ограничивают MIME, размер, разрешение и число изображений. Ошибки формы — 422 с привязкой к полям; неавторизован — 401/redirect, запрещено — 403, отсутствует — 404, конфликт порядка/публикации — 409.

## Будущие endpoint группы

Player-to-User account linking, daily start/complete, достижения, магазин, покупки, rewarded ads и leaderboard проектируются в своих фазах. Привязка аккаунта должна связать `User` с существующим `Player`, сохранив player ID, токены и игровой прогресс; стратегия конфликтов аккаунтов фиксируется до реализации linking endpoint. Покупки подтверждаются backend через receipt платформы/подписанный webhook; клиентское сообщение об успешной покупке само по себе не зачисляет валюту. События аналитики принимаются внутренним адаптером/серверными событиями, клиент не может сообщить критичные игровые результаты.
