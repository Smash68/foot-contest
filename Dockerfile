FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["tail", "-f", "/dev/null"]