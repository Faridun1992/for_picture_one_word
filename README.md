# Проект X (Laravel 13)

Веб‑приложение на Laravel с фронтендом на Vite. Стек включает MySQL, Redis, ClickHouse и Manticore Search, а также Docker Compose для локальной и stage‑среды. Этот README описывает процессы установки, разработки, тестирования и деплоя для данного репозитория.

Последнее обновление: 2026-04-05

## Обзор
- Бэкенд: PHP 8.4, Laravel 13
- Фронтенд: Vite 5, ES Modules, SCSS; Bootstrap 5
- Поиск/индексация: Laravel Scout + Manticore
- Хранилище данных: MySQL 8.4, Redis
- Аналитика/OLAP: ClickHouse
- Файловое хранилище: Yandex Object Storage (S3-совместимое, через league/flysystem-aws-s3-v3)
- Процессы/очереди: Supervisor + Laravel Horizon + очереди Redis
- Инструменты разработки: Docker Compose, цели Makefile, PHPUnit

Значимые пакеты (бэкенд):
- jeroennoten/laravel-adminlte (UI AdminLTE)
- spatie/laravel-permission (роли/разрешения)
- opcodesio/log-viewer (просмотр логов)
- romanstruk/manticore-scout-engine (драйвер Scout)
- glushkovds/phpclickhouse-laravel (клиент ClickHouse)
- laravel/horizon (мониторинг очередей)
- laravel/ui, laravel/scout, predis/predis
- web-token/jwt-framework (JWT)
- pulkitjalan/ip-geolocation (геолокация по IP)

Значимые пакеты (фронтенд):
- apexcharts (графики)
- tinymce (WYSIWYG-редактор)
- swiper (слайдер)
- tus-js-client (загрузка файлов по протоколу tus)
- simplebar (кастомный скроллбар)

## Требования
Проект можно запускать нативно или через Docker.

Нативно (без Docker):
- PHP 8.4 со следующими расширениями: curl, dom, exif, iconv, libxml, openssl, simplexml, zend-opcache
- Composer 2
- Node.js 20+ (Vite требует современный Node; Docker‑образ использует Node 22)
- MySQL 8.4, Redis, ClickHouse, Manticore (локально опционально; в Docker‑настройке требуется)

Docker:
- Docker Desktop 4+
- Docker Compose v2 (docker compose)

## Быстрый старт (Docker)
Репозиторий содержит docker-compose.yml и шорткаты Makefile.

1) Скопируйте .env и скорректируйте значения:
- cp .env.example .env
- Обновите APP_URL (по умолчанию http://project-x.loc) и при необходимости креды DB/Redis

2) Запустите стек:
- make up-local

3) Установите PHP‑зависимости (в шелле контейнера php):
- make php-local
- composer install
- php artisan key:generate (если еще не задан)
- php artisan migrate

4) Установите и соберите фронтенд‑ассеты:
- make install-local
- make dev-local (для горячей перезагрузки) или make build-local (продакшн‑сборка)

5) Откройте приложение:
- http://localhost (nginx -> public/)
- Vite dev‑сервер доступен на http://localhost:3000 (HMR‑хост настроен как project-x.loc в vite.config.js; при необходимости поправьте hosts или конфиг Vite)

Остановка:
- make down-local

Для stage‑варианта используется docker-compose-stage.yml с аналогичными целями Makefile (up-stage, down-stage и т. д.).

## Локальный запуск без Docker (опционально)
- Скопируйте env: cp .env.example .env и укажите хосты DB/Redis как localhost
- Установка PHP‑зависимостей: composer install
- Генерация ключа: php artisan key:generate
- Миграции: php artisan migrate
- Установка Node‑зависимостей: npm install
- Запуск дев‑серверов:
  - php artisan serve (обслуживает public/ на http://127.0.0.1:8000)
  - npm run dev (Vite на http://127.0.0.1:3000)

Примечание: Некоторые фичи требуют ClickHouse и Manticore. Если вы не запускаете их локально, отключите зависящие фичи или задайте соответствующие переменные окружения. TODO: Задокументировать фича‑флаги для работы без этих сервисов, если поддерживается.

## Скрипты и инструменты
Composer (серверная часть):
- composer install / update
- php artisan migrate
- php artisan queue:work (использует Redis)
- php artisan horizon (мониторинг очередей через Laravel Horizon)
- php artisan tinker

NPM (фронтенд):
- npm run dev — запуск Vite dev‑сервера
- npm run build — продакшн‑сборка

Makefile (шорткаты для Docker):
- up-local / down-local — старт/остановка локального стека
- php-local — shell в контейнер php (bash)
- install-local — npm install внутри контейнера node
- dev-local / build-local — запуск Vite внутри контейнера node
- up-stage / down-stage / php-stage / install-stage / dev-stage / build-stage — эквиваленты для stage

## Переменные окружения
См. .env.example для полного списка. Ключевые переменные:
- Приложение: APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_TIMEZONE, APP_LOCALE
- Сессии/Кэш/Очереди: SESSION_DRIVER (database), CACHE_STORE (redis), QUEUE_CONNECTION (redis)
- База данных (MySQL): DB_HOST, DB_PORT (3306), DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_ROOT_PASSWORD
- Redis: REDIS_HOST, REDIS_PORT (6379), REDIS_PASSWORD, REDIS_CLIENT (predis)
- Почта: MAIL_MAILER (по умолчанию log), MAIL_HOST, MAIL_PORT, MAIL_FROM_ADDRESS, MAIL_FROM_NAME
- Vite: VITE_APP_NAME
- ClickHouse: CLICKHOUSE_HOST (например, clickhouse:8123), CLICKHOUSE_USER, CLICKHOUSE_PASSWORD, CLICKHOUSE_DB, CLICKHOUSE_HTTP_PORT, CLICKHOUSE_TCP_PORT
- Manticore (поиск Scout): MANTICORE_ENGINE (mysql-builder), MANTICORE_MYSQL_HOST, MANTICORE_MYSQL_PORT (9306)
- Отображение пользователя в Docker: UID, GID, PROJECT_USER
- Сторонние API: SENDSAY_URL, SENDSAY_API_KEY, KINESCOPE_API_TOKEN, ISPMANAGER_USERNAME, ISPMANAGER_PASSWORD, ISPMANAGER_DOMAIN

TODO:
- Описать, какие фичи используют SENDSAY, KINESCOPE и ISPManager, и какие нужны шаги настройки.

## Точки входа и структура
- HTTP‑вход: public/index.php (обслуживается nginx или php -S через artisan serve)
- Web-роуты объявлены в routes/web.php; AdminLTE dashboard доступен только ролям Admin и Super Admin.
- Контроллеры: app/Http/Controllers (пространства имен Admin, User, Guest)
- Представления: resources/views (Blade)
- Ассеты: resources/js и resources/css (группированы по guest/admin/user), собираются Vite (vite.config.js автодетектит)
- Консоль: artisan; кастомные команды в app/Console/Commands
- База данных: миграции, фабрики, сиды в database/

Пример дерева (частично):
- app/
  - Console/Commands/UpdateFinanceStatisticCommand.php
  - Http/Controllers/{Admin,User,Guest}/...
  - Services/NoteService.php
- resources/
  - js/{guest,admin,user}/*.js
  - css/{guest,admin,user}/*.scss
  - views/{guest,admin}/...
- routes/web.php (+ includes)
- public/
- docker/ (nginx, php-fpm, supervisor, mysql init)

## Заметки по разработке
- В vite.config.js HMR‑хост задан как project-x.loc. Для локальной разработки можно:
  - Добавить 127.0.0.1 project-x.loc в hosts,
  - Обновить vite.config.js -> server.hmr.host на localhost, или
  - Соответственно настроить APP_URL и прокси.
- Очереди: локальный Docker Compose запускает `supervisor_game` с Laravel Horizon; задачи обработки изображений выполняются отдельным worker на очереди `images`. Мониторинг доступен через Laravel Horizon (`php artisan horizon`).
- Кэширование/Сессии: по умолчанию настроены на Redis в .env.example.

## Тесты
- В Docker: `docker compose exec php php artisan test` (или `docker compose exec php ./vendor/bin/phpunit`). Имя хоста `mysql_game` доступно внутри Docker-сети.
- С хоста при опубликованном MySQL порте: `DB_HOST=127.0.0.1 php artisan test` (или `DB_HOST=127.0.0.1 ./vendor/bin/phpunit`). Без переопределения хост использует Docker-only hostname `mysql_game`, который с хоста не разрешается.
- Конфигурация PHPUnit определяет наборы Unit и Feature; `APP_ENV=testing`, `DB_CONNECTION=mysql`, база `testing`. Перед тестами убедитесь, что MySQL запущен и тестовая БД существует.

## Деплой
- Используйте цели build-stage/dev-stage с docker-compose-stage.yml в качестве отправной точки.
- Убедитесь, что APP_KEY задан, а каталоги storage/ и bootstrap/cache доступны для записи.
- Сборка ассетов: npm run build (или make build-stage) и настройка nginx на раздачу из public/.
- TODO: Добавить детали CI/CD и шаги по подготовке серверов.

## Лицензия
Проект распространяется по лицензии MIT (см. composer.json). TODO: Добавить файл LICENSE в репозиторий.
