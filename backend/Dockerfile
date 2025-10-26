FROM php:8.3-fpm

# 必要なものをインストール
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    vim \
    libpq-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

# Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Xdebug 設定
RUN set -eux; \
    echo "xdebug.mode=develop,debug,coverage" > /usr/local/etc/php/conf.d/xdebug.ini; \
    echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini; \
    echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini; \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini; \
    echo "xdebug.log_level=0" >> /usr/local/etc/php/conf.d/xdebug.ini

