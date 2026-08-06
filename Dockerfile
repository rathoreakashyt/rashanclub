# ============================================================
# rashankidukan - Retail POS (Off POS v11.0)
# PHP 8.2 EXACT — installer requires PHP >= 8.2.0 && < 8.3.0
# ============================================================
FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP config for production-like installer environment
RUN echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "allow_url_fopen = On" >> /usr/local/etc/php/conf.d/custom.ini

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Create www user/group (UID/GID 1000)
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

# Copy project files with correct ownership
COPY --chown=www:www . /var/www

# Ensure critical directories exist and are writable
RUN mkdir -p /var/www/storage/app/public \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chown -R www:www /var/www/storage \
    && chown -R www:www /var/www/bootstrap/cache

USER www

EXPOSE 9000

CMD ["php-fpm"]
