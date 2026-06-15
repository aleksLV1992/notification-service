FROM php:8.5-fpm-bookworm

ARG WWWGROUP=1000
ARG WWWUSER=1000

# Установка системных зависимостей и PHP расширений
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

# Установка Redis расширения из исходников
RUN git clone --depth 1 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание пользователя для запуска приложения
RUN useradd -G www-data,root -u ${WWWUSER} -d /home/appuser appuser || true
RUN mkdir -p /home/appuser/.composer && \
    chown -R appuser:appuser /home/appuser

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование файлов проекта
COPY --chown=appuser:www-data . /var/www/html

# Настройка прав для Laravel
RUN mkdir -p storage/app/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && \
    chown -R appuser:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Настройка PHP-FPM для прослушивания на всех интерфейсах
RUN echo "listen = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/www.conf

# Переключение на пользователя appuser
USER appuser

EXPOSE 9000

CMD ["php-fpm"]
