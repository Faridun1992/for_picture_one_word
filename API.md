# REST API v1

Статус: health, guest session/revoke, catalog, level attempts и hints реализованы. Базовый URL `/api/v1`. JSON UTF-8. Время ISO 8601 UTC. Ответы ошибок единообразны: `{"message":"...","code":"...","errors":{}}`. Токен — `Authorization: Bearer …`. Игровой bearer token принадлежит `Player`; все игровые маршруты ограничиваются по IP и player ID. Существующая web-аутентификация `User` не даёт доступ к игровым маршрутам.

## Общие правила

- `Accept-Language: ru`, `tj` или `en`; неизвестный язык даёт 422, отсутствующий перевод — fallback на `en` только если это явно задано каталогом, иначе 404/422.
- `Idempotency-Key` обязателен для `POST` попытки и подсказки; 8–80 ASCII символов. Завершённый status/body хранится 30 дней с `completed_at`; повтор в течение срока с теми же операцией и телом возвращает сохранённый результат без повторного выполнения. Ключ с другой операцией/телом получает 409 `idempotency_key_reused`; незавершённая операция получает 409 `idempotency_request_in_progress` и не удаляется по TTL. Это позволяет клиенту безопасно повторить offline-запрос после сетевого сбоя.
- API не принимает `coins`, `balance`, `reward`, `is_correct`, `completed`, `next_level_id` или цену подсказки от клиента.
- Пагинация cursor-based. Только опубликованные уровни доступны игроку. Guest session ограничена 10 запросами в минуту на IP. Защищённые игровые маршруты требуют bearer principal типа `Player` и ограничены 120 запросами в минуту на игрока и 600 на IP; превышение возвращает 429 `too_many_requests`. Админ API защищён web-сессией и authorization policy; ниже перечисленные `/admin` маршруты не используют мобильный токен.

## Игрок

### `GET /health`

Полный URL: `GET /api/v1/health`. Auth: нет. Response 200: `{"status":"ok"}` для проверки доступности маршрутизации. Не проверяет БД/Redis и не раскрывает внутренние сведения. Все ошибки `/api/*` возвращают `{ "message": "...", "code": "...", "errors": {} }`; при 422 поле `errors` содержит массивы сообщений по именам полей. 5xx не раскрывают внутренний текст исключения. Основные коды: `validation_failed` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `conflict` (409), `too_many_requests` (429), `internal_server_error` (5xx). Остальные web-маршруты сохраняют стандартное поведение Laravel.

### `POST /auth/guest`

Реализовано в TASK-030/032. Auth: нет. Создаёт `Player`, кошелёк с одноразовой наградой **300 Coins** и append-only записью ledger без строки в `users`, выпускает Sanctum bearer token для `Player`. Повторная авторизация не пополняет существующий профиль; каждый запрос создаёт отдельного гостя. Request: `{"locale":"tj","device_name":"Phone"}`; оба поля optional, locale defaults to `ru`, `device_name` max 100 и используется только как имя токена, не как identity. Response 201: `{"data":{"token":"…","player":{"id":123,"locale":"tj"}}}`. Ошибки 422 invalid locale/device name, 429 после 10 запросов в минуту с одного IP.

### `DELETE /auth/session`

Реализовано в TASK-030. Auth: bearer игрока. Request отсутствует. Response 204 удаляет только текущий Sanctum token; другие сессии игрока остаются активными. 401 без/с невалидным токеном, 403 для токена `User`.

### `GET /me`

Auth: bearer игрока. Principal берётся из текущего Sanctum token (`auth()->user()`), игнорируя переданный клиентом `player_id`; response 200: player ID, locale, server wallet balance, настройки, `current_level_id`. Не включает сведения или секреты `User`. 401 без/с невалидным токеном; 403 при principal не типа `Player`.

### `PATCH /me/settings`

Auth: bearer. Request: `{"locale":"ru","sound_enabled":true,"haptics_enabled":true,"theme":"system"}`; необязательные поля. Response 200: сохранённые настройки. Валидация enum/boolean, 422; 401.

### `GET /categories`

Реализовано в TASK-031. Auth: bearer игрока. Query optional `locale` (default player locale), `cursor`, `limit` (1–50, default 20). Response 200: `data` содержит только активные категории с переводом для запрошенной локали, `id`, `slug`, `name`, числом опубликованных доступных уровней; `meta.next_cursor` и `meta.previous_cursor` — encoded cursor или null. Level count исключает уровни без перевода или ровно четырёх image positions. 401/422.

### `GET /levels`

Реализовано в TASK-031/043. Auth: bearer игрока. Query optional `locale` (default player locale), `category_id`, `after_id`, `cursor`, `limit` (1–50, default 20). Возвращает опубликованные уровни только при наличии category/level translation для языка и четырёх позиций изображений. `data` содержит id, порядок, сложность, локализованную категорию, `answer_length` в Unicode graphemes, `letter_tiles`, четыре упорядоченных image `{position,url,thumbnail_url,width,height}`, `locale` и `content_version`. `url` указывает на display-вариант, когда он готов, иначе на оригинал; `thumbnail_url` равен null до завершения обработки. Private storage выдаёт подписанные URL на 24 часа; public storage использует обычный URL. Ответы `answer_display` и `answer_normalized` исключены. Cursor находится в `meta`. 401, 404 для неизвестной категории, 422 для параметров, 429.

### `GET /levels/{level}`

Реализовано в TASK-031. Auth: bearer игрока. Response 200: карточка одного опубликованного и полного уровня; содержит `letter_tiles`, четыре объекта `{position,url,width,height}`, `answer_length`, locale, category translation и `content_version`. Private storage выдаёт подписанный URL на 24 часа; public storage использует обычный URL. `answer_display` и `answer_normalized` никогда не возвращаются. 404 если уровень не опубликован, неполон или недоступен для локали; 401/429.

### `POST /levels/{level}/attempts`

Реализовано в TASK-032. Auth: bearer; `Idempotency-Key` обязателен. Request: `{"answer":"ГУРБА"}` (UTF-8, max 100 graphemes). Сервер нормализует строку и сравнивает ответ. Неверный ответ увеличивает счётчик попыток, Coins не меняются. Первый правильный ответ атомарно начисляет **+10 Coins за правильный ответ** и **+50 Coins за завершение загадки**, а при достижении 50/100/500/1000 решённых загадок — одноразовый milestone (+150/+300/+300/+500). Response 200 содержит correctness, completed_at, разбивку награды/итоговый баланс, progress и next_level_id. Повторное прохождение не выдаёт награды повторно. Ошибки: 401, 404, 409 для завершённой загадки при несовпадающем состоянии, 422 формат/длина, 429 throttle. Повтор ключа идемпотентен.

### `POST /levels/{level}/hints`

Реализовано в TASK-033. Auth: bearer; `Idempotency-Key` обязателен. Request: `{"type":"reveal_letter","position":2}`, `{"type":"remove_wrong_letters"}` или `{"type":"reveal_answer"}`. Серверные цены: 30/40/100 Coins. Reveal letter открывает ещё не открытую букву. Remove wrong letters удаляет до двух неправильных плиток (количество конфигурируется), выбранных сервером из набора уровня; клиентское состояние плиток не принимается. Reveal answer показывает ответ и завершает загадку с наградой за завершение. Если эффект исчерпан, сервер отвечает 200 `charged:false`, `cost:0`, без списания. Списание, изменение подсказки и награда за завершение атомарны. 401, 404, 409 если уровень уже завершён, 422 неизвестный тип/позиция или `insufficient_coins` без изменений баланса, 429.

### `GET /progress`

Progress endpoint реализован в TASK-034; схема серверного хранения прогресса добавлена в TASK-025.

Auth: bearer игрока. Query optional `cursor`, `limit` 1–100 (default 50). Response 200: `data.current_level_id`, баланс, массив progress (`level_id`, sequence, status, attempts, hints, timestamps), статистика total/completed/attempts; `meta` содержит cursor. `current_level_id` — активный доступный уровень или первый доступный незавершённый, null если больше нет доступных уровней. Только текущий `Player`. 401/422.

`GET /me` и `PATCH /me/settings` реализованы в TASK-034. Новые игроки получают `sound_enabled=true`, `haptics_enabled=true`, `theme=system`; язык по умолчанию `ru`. Допустимые темы: `system`, `light`, `dark`.

### `GET /daily`

Auth: bearer. Daily endpoint остаётся задачей отдельной фазы. Награда за первое успешное прохождение конкретного daily challenge утверждена: +100 Coins. Пропуск не отнимает Coins и не сбрасывает основной прогресс. Реализация назначения/прохождения — TASK-060/061. 401.

### `GET /leaderboard`

Не входит в MVP. Вводится вместе с рейтингом; контракт расширить при старте соответствующей задачи, не выдавать персональные данные пользователей.

## Администрирование (web)

AdminLTE dashboard `GET /home` требует web session и роль `Admin` или `Super Admin`; остальные пользователи получают 403, неавторизованные перенаправляются на login. Публичная регистрация отключена. TASK-041 реализует `GET /admin/levels`, `GET /admin/levels/create`, `POST /admin/levels`, `GET /admin/levels/{level}/edit` и `PUT /admin/levels/{level}`. TASK-042 добавляет `POST /admin/levels/{level}/images`: только для черновиков, принимает все четыре изображения (позиции 1–4) в JPEG/PNG/WebP, размером до 5 MiB и разрешением 320–4096 px по каждой стороне; файлы хранятся приватно через Laravel Storage, в БД фиксируются ключ/позиция/MIME/dimensions. Загрузка нового комплекта заменяет прежние четыре изображения. TASK-043 запускает для каждого оригинала Horizon job на очереди `images`; он создаёт `thumbnail` (до 320×320) и `display` (до 1280×1280) в исходном формате, не изменяя оригинал. TASK-044 добавляет `POST /admin/levels/{level}/publish` и `POST /admin/levels/{level}/archive`: публикация проверяет активную локализованную категорию, все переводы из `game.supported_locales`, покрытие правильного ответа буквенными плитками и доступность оригиналов четырёх изображений; архивирование меняет статус без удаления прогресса или файлов. TASK-045 добавляет `POST /admin/levels/{level}/move` с `direction=up|down` для изменения порядка опубликованных уровней. TASK-046 добавляет `GET /admin/statistics/levels`: только агрегаты прохождений по уровням, без данных отдельных игроков. Все административные действия проверяют доступ server-side.

## Будущие endpoint группы

Player-to-User account linking, daily start/complete, достижения, магазин, покупки, rewarded ads и leaderboard проектируются в своих фазах. Rewarded ad награждает +50 Coins, ограничен пятью успешными просмотрами в сутки на Player и требует подтверждения SDK/серверной рекламной интеграции; клиентский флаг не считается подтверждением. Interstitial не показываются после каждого уровня: ориентир — после 4–6 загадок, cooldown минимум 90 секунд, не во время решения, не сразу после запуска или rewarded ad; Premium отключает Interstitial и не даёт бесконечные бесплатные подсказки. Premium также допускает будущие косметические возможности и стартовый бонус. Рекламная интеграция остаётся отдельной задачей от wallet/business logic. Привязка аккаунта должна связать `User` с существующим `Player`, сохранив player ID, токены и игровой прогресс; стратегия конфликтов аккаунтов фиксируется до реализации linking endpoint. Покупки подтверждаются backend через receipt платформы/подписанный webhook; клиентское сообщение об успешной покупке само по себе не зачисляет валюту. События аналитики принимаются внутренним адаптером/серверными событиями, клиент не может сообщить критичные игровые результаты.
