FROM php:8.2-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash curl zip unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    oniguruma-dev icu-dev libzip-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring bcmath gd intl pcntl zip opcache

# Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer first (cache optimization)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy rest of app
COPY . .

# Final optimize
RUN composer dump-autoload --optimize

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
