FROM php:8.4-fpm-alpine

# Install system dependencies and build dependencies
RUN apk add --no-cache \
    bash \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        zip \
        opcache \
        gd \
        bcmath

# Install APCu via PECL (requires build tools, cleaned up after install)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

# Copy custom php.ini config
COPY app/docker/prod/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy custom php-fpm override pool configuration
COPY app/docker/prod/php/zz-docker-override.conf /usr/local/etc/php-fpm.d/zz-docker-override.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Install Composer and dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Set permissions for log and cache directories
RUN mkdir -p app/logs app/cache \
    && chmod -R 775 app/logs app/cache \
    && chown -R www-data:www-data app/logs app/cache
