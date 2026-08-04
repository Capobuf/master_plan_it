FROM php:8.3.32-cli-bookworm

LABEL maintainer="Laravel"

ARG WWWGROUP=1000

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_COMMAND="/usr/local/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        gosu \
        libicu-dev \
        libzip-dev \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_mysql zip \
    && groupadd --force -g "${WWWGROUP}" sail \
    && useradd -ms /bin/bash --no-user-group -g "${WWWGROUP}" -u 1337 sail \
    && git config --global --add safe.directory /var/www/html \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7.1 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22.14.0-bookworm-slim /usr/local /usr/local

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
