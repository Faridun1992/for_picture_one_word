# REST API v1

Статус: контракт проектирования. Базовый URL задаётся конфигурацией окружения, например `/api/v1`. JSON UTF-8. Время ISO 8601 UTC. Ответы ошибок единообразны: `{"message":"...","code":"...","errors":{}}`. Токен — `Authorization: Bearer …`. Все игровые маршруты ограничиваются по IP и user ID.

## Общие правила

- `Accept-Language: ru`, `tj` или `en`; неизвестный язык даёт 422, отсутствующий перевод — fallback на `en` только если это явно задано каталогом, иначе 404/422.
- `Idempotency-Key` обязателен для `POST` завершения уровня и подсказки; 8–80 ASCII символов. Тот же ключ и тот же запрос повторяют прежний статус/ответ; тот же ключ с другим телом — 409 `idempotency_key_reused`.
- API не принимает `coins`, `is_correct`, `completed`, `next_level_id` или цену подсказки от клиента.
- Пагинация cursor-based. Только опубликованные уровни доступны игроку. Админ API защищён web-сессией и authorization policy; ниже перечисленные `/admin` маршруты не используют мобильный токен.

## Игрок

### `GET /health`

Полный URL: `GET /api/v1/health`. Auth: нет. Response 200: `{"status":"ok"}` для проверки доступности маршрутизации. Не проверяет БД/Redis и не раскрывает внутренние сведения. Все ошибки `/api/*` возвращают `{ "message": "...", "code": "...", "errors": {} }`; при 422 поле `errors` содержит массивы сообщений по именам полей. 5xx не раскрывают внутренний текст исключения. Основные коды: `validation_failed` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `conflict` (409), `too_many_requests` (429), `internal_server_error` (5xx). Остальные web-маршруты сохраняют стандартное поведение Laravel.

### `POST /auth/guest`

Auth: нет. Создаёт гостевой профиль и выдает bearer token. Request: `{"locale":"tj","device_name":"Phone"}` (device_name optional, max 100). Response 201: `{"data":{"token":"…","user":{"id":123,"locale":"tj"}}}`. Ошибки 422 locale, 429 rate limit. Повтор не идемпотентен; каждый новый токен создаёт/возвращает отдельный гостевой профиль по политике реализации.

### `DELETE /auth/session`

Auth: bearer. Request отсутствует. Response 204 отзывает текущий токен; 401 при истёкшей сессии.

### `GET /me`

Auth: bearer. Response 200: ID, locale, server wallet balance, настройки, `current_level_id`. Не включает секреты. 401.

### `PATCH /me/settings`

Auth: bearer. Request: `{"locale":"ru","sound_enabled":true,"haptics_enabled":true,"theme":"system"}`; необязательные поля. Response 200: сохранённые настройки. Валидация enum/boolean, 422; 401.

### `GET /categories`

Auth: bearer. Query `locale`, `cursor`, `limit` (1–50, default 20). Response 200: активные категории с `id`, `slug`, `name`, доступным числом уровней и курсором. 401/422.

### `GET /levels`

Auth: bearer. Query `locale`, optional `category_id`, `after_id`, `cursor`, `limit` (max 50). Возвращает только опубликованные уровни без ответов: id, порядок, сложность, локализованное имя категории, длину ответа, плитки, четыре URL изображений и ETag/version. 401, 404 для неизвестной категории, 422 для параметров, 429.

### `GET /levels/{level}`

Auth: bearer. Response 200: карточка одного опубликованного уровня, `letter_tiles`, четыре объекта `{position,url,width,height}`, `answer_length`, locale и `content_version`. `answer_display` и `answer_normalized` никогда не возвращаются. 404 если уровень не опубликован/недоступен; 401/429.

### `POST /levels/{level}/attempts`

Auth: bearer; `Idempotency-Key` обязателен. Request: `{"answer":"ГУРБА"}` (UTF-8, max 100 graphemes). Сервер нормализует строку и сравнивает ответ. Response 200 неверно: `{"data":{"correct":false,"progress":{...}}}`; правильно: `{"data":{"correct":true,"completed_at":"…","reward":{"coins":10,"balance":40},"next_level_id":101}}`. Награда конфигурируется сервером и выдаётся лишь один раз. Ошибки: 401, 404, 409 для повторного завершения с несогласованным состоянием, 422 формат/длина, 429 throttle. Повтор ключа идемпотентен.

### `POST /levels/{level}/hints`

Auth: bearer; `Idempotency-Key` обязателен. Request: `{"type":"reveal_letter","position":2}` или `{"type":"remove_wrong_letters"}`. Стоимость серверная. Response 200: применённая подсказка, изменённое состояние плиток, новый баланс. 401, 404, 409 если уровень уже завершён, 422 неизвестный тип/позиция, 429, 422 `insufficient_coins` без изменений баланса. Транзакция и идемпотентность обязательны.

### `GET /progress`

Auth: bearer. Query optional `cursor`, `limit` max 100. Response 200: `current_level_id`, баланс, список прогресса с курсором, общая статистика. Только текущий пользователь. 401/422.

### `GET /daily`

Auth: bearer. MVP: 200 с `{"data":{"available":false}}` до включения daily phase; после неё возвращает назначенную задачу и персональный статус. 401.

### `GET /leaderboard`

Не входит в MVP. Вводится вместе с рейтингом; контракт расширить при старте соответствующей задачи, не выдавать персональные данные пользователей.

## Администрирование (web)

Админ CRUD работает под web session + CSRF, с ролями/политиками и проверкой каждого действия. Доступы: `GET/POST /admin/levels`, `GET/PATCH/DELETE /admin/levels/{level}`, `POST /admin/levels/{level}/images`, `POST /admin/levels/{level}/publish`, `POST /admin/levels/reorder`, `GET /admin/statistics/levels`. Форма принимает категорию, сложность, статус, переводы, правильный ответ и плитки. Удаление опубликованного уровня архивирует. Загрузки ограничивают MIME, размер, разрешение и число изображений. Ошибки формы — 422 с привязкой к полям; неавторизован — 401/redirect, запрещено — 403, отсутствует — 404, конфликт порядка/публикации — 409.

## Будущие endpoint группы

Daily start/complete, достижения, магазин, покупки, rewarded ads и leaderboard проектируются в своих фазах. Покупки подтверждаются backend через receipt платформы/подписанный webhook; клиентское сообщение об успешной покупке само по себе не зачисляет валюту. События аналитики принимаются внутренним адаптером/серверными событиями, клиент не может сообщить критичные игровые результаты.
