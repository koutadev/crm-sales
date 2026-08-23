FROM php:8.3-fpm-bookworm

# Linux のバインドマウントで所有権がずれないよう、ホストの UID/GID に合わせる
# (macOS / Windows の Docker Desktop では指定不要)
ARG UID=1000
ARG GID=1000

# ---- OS パッケージ ---------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        gnupg \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# ---- PHP 拡張 --------------------------------------------------------------
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache

# ---- Node.js (Vite / Tailwind のビルド用) ----------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# ---- Composer --------------------------------------------------------------
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

# ---- www-data をホストユーザーに合わせる -----------------------------------
RUN if [ "$(id -u www-data)" != "${UID}" ]; then \
        groupmod -o -g "${GID}" www-data \
        && usermod -o -u "${UID}" -g "${GID}" www-data; \
    fi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
