FROM php:8.2-fpm

# Extensions nécessaires
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Utilisateur non-root
RUN useradd -u 1000 -m appuser
USER appuser

WORKDIR /var/www

CMD ["php-fpm"]