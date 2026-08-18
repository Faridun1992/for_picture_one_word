FROM php:8.4.20-cli

ARG user
ARG uid
ARG gid

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libgd-dev \
    libzip-dev \
    unzip \
    curl \
    gnupg2 \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    libavif-bin \
    libmagickwand-dev \
    supervisor \
    && pecl install imagick-3.8.1 \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install pdo_mysql bcmath zip exif pcntl \
    && docker-php-ext-configure gd --with-freetype --with-webp --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN groupadd -g ${gid} ${user} \
    && useradd -u ${uid} -ms /bin/bash -g ${user} -G www-data ${user}

# Контекст билда — корень репо.
# PHP CLI для Horizon работает с дефолтными настройками (без opcache и т.п.).
# Если понадобится кастомный php.ini для supervisor — создать
# docker/supervisor/php-conf.d/ с .ini-файлами и вернуть COPY.

RUN mkdir -p /var/log/supervisor /etc/supervisor/conf.d

# supervisor-программы (laravel-horizon и т.д.) — bind-mount в compose RO,
# но COPY нужен для изолированной сборки без compose.
COPY docker/supervisor/conf.d/ /etc/supervisor/conf.d/

WORKDIR /var/www
# WORKDIR создаёт /var/www от root; композер ниже работает из-под $user
RUN chown ${user}:www-data /var/www

# Слой зависимостей (cache-friendly)
COPY --chown=${user}:www-data composer.json composer.lock /var/www/
USER ${user}
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

# Слой кода
COPY --chown=${user}:www-data . /var/www
# --no-scripts: .env монтируется в рантайме, artisan package:discover в
# post-autoload-dump упадёт на сервисах, читающих config() в register().
RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

USER root
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
