FROM php:8.3-fpm

# 必要なものをインストール
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    vim \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

