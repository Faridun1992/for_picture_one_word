FROM nginx:1.28.3

# Контекст билда — корень репо (см. docker-compose-stage.yml).
COPY docker/nginx/prod/conf.d /etc/nginx/conf.d/
COPY docker/nginx/prod/nginx.conf /etc/nginx/nginx.conf

# Статика (public/) печётся в образ; в рантайме эти же файлы перекрывают конфиги
# из bind-mount'а conf.d/nginx.conf (их оставляем как hot-reload).
# storage/ приходит как named volume (шарится с php) — нужен для /public/storage/...
COPY public /var/www/public

WORKDIR /var/www
