# ============================================================
# rashankidukan - Retail POS (Off POS v11.0)
# PHP 8.2 EXACT — installer requires PHP >= 8.2.0 && < 8.3.0
# ============================================================
# NOTE: php:8.2-fpm (trixie, 2026-08-03 build) is BROKEN upstream (empty entrypoint/config files).
#       Using stable bookworm variant instead.
FROM php:8.2-fpm-bookworm

# System dependencies
# Note 1: deb.debian.org serves corrupted/truncated responses on some networks; use kernel.org mirror + security.debian.org
# Note 2: php:8.2-fpm trixie image (2026-08-03) ships empty /var/lib/dpkg/info/format -> repair before apt
# Note 3: noninteractive + force-confold needed (image lacks apt-utils/debconf frontends; adduser.conf prompt)
RUN echo "1" > /var/lib/dpkg/info/format \
    && sed -i 's|http://deb.debian.org/debian-security|http://security.debian.org/debian-security|g' /etc/apt/sources.list.d/*.sources \
    && sed -i 's|http://deb.debian.org/debian|http://mirrors.edge.kernel.org/debian|g' /etc/apt/sources.list.d/*.sources \
    && DEBIAN_FRONTEND=noninteractive apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y -o Dpkg::Options::=--force-confold \
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
# Note 4: upstream php:8.2-fpm trixie image ships EMPTY docker-php-entrypoint -> recreate the standard one
RUN printf '#!/bin/sh\nset -e\nif [ "${1#-}" != "$1" ]; then set -- php-fpm "$@"; fi\nexec "$@"\n' > /usr/local/bin/docker-php-entrypoint \
    && chmod +x /usr/local/bin/docker-php-entrypoint \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
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

# Install cron for Laravel scheduler (busy:import every 30 min)
RUN DEBIAN_FRONTEND=noninteractive apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y cron \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Laravel scheduler cron — runs every minute, scheduler decides what to execute
RUN echo "* * * * * www cd /var/www && php artisan schedule:run >> /var/log/laravel-cron.log 2>&1" \
    > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler \
    && crontab /etc/cron.d/laravel-scheduler

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

# entrypoint: cron (background) + php-fpm (foreground)
COPY --chown=root:root entrypoint.sh /entrypoint.sh
USER root
RUN chmod +x /entrypoint.sh
CMD ["/entrypoint.sh"]
