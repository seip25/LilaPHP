FROM php:8.4-fpm-alpine

# Install system dependencies and build dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    sqlite-dev \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        zip \
        gd \
        bcmath

# Install APCu via PECL (requires build tools, cleaned up after install)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy custom php.ini config
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
