FROM php:8.5-fpm-bookworm

ARG WWWGROUP=1000
ARG WWWUSER=1000

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    liblzf-dev \
    redis-tools \
    libicu-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql pgsql zip mbstring exif pcntl bcmath gd sockets intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u ${WWWUSER} -d /home/appuser appuser || true
RUN mkdir -p /home/appuser/.composer && \
    chown -R appuser:appuser /home/appuser

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-dev

COPY --chown=appuser:www-data . /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

RUN mkdir -p storage/app/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && \
    chown -R appuser:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

RUN echo "listen = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/www.conf

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
