FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-dev postgresql-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && pecl install xdebug \
    && apk del linux-headers $PHPIZE_DEPS

COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["tail", "-f", "/dev/null"]