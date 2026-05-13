FROM composer:2 AS composer-deps

WORKDIR /app

COPY rootfs/app/composer.json rootfs/app/composer.lock ./

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --ignore-platform-req=php

FROM php:8.5-cli-alpine3.23

ENV SCHEDULE="*/10 * * * *" \
    CRON_CMD="php /app/updater.php" \
    DOMAIN="" \
    MODE="@" \
    IPV4="yes" \
    IPV6="no" \
    TTL="0" \
    FORCE="no"

COPY rootfs/ /
COPY --from=composer-deps /app/vendor /app/vendor

ENTRYPOINT ["/entrypoint.sh"]

CMD ["/usr/sbin/crond", "-f"]
