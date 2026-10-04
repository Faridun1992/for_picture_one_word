FROM php:8.5.11-fpm

ARG user
ARG uid
ARG gid

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libwebp-dev \
    unzip \
    curl \
    gnupg2 \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    libavif-bin \
    libfcgi-bin \
    && docker-php-ext-install \
        pdo_mysql \
        pcntl \
        intl \
    && docker-php-ext-configure exif \
    && docker-php-ext-install exif \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer через официальный образ — безопаснее
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

RUN groupadd -g $gid $user && \
    useradd -u $uid -ms /bin/bash -g $user -G www-data $user

COPY ./conf.d-local /usr/local/etc/php/conf.d/

# PHP-FPM pool настройки (производительность)
COPY ./fpm.d /usr/local/etc/php-fpm.d/

COPY --chown=$user:www-data . /var/www

USER $user

WORKDIR /var/www
