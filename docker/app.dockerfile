FROM php:8.3-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    vim \
    libpq-dev \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Установка расширений
RUN docker-php-ext-install pdo_pgsql

RUN pecl install redis \
    && docker-php-ext-enable redis

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo 'xdebug.mode=coverage' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo 'xdebug.client_host=host.docker.internal' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \

WORKDIR /var/www

CMD ["php-fpm"]
