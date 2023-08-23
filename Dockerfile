FROM php:8-cli-alpine

ENV SCHEDULE="*/10 * * * *" \
    CRON_CMD="php /app/updater.php" \
    DOMAIN="" \
    MODE="@" \
    IPV4="yes" \
    IPV6="no" \
    TTL="0" \
    CUSTOMER_ID="" \
    API_KEY="" \
    API_PASSWORD="" \
    FORCE="no"

ADD rootfs/ /

ENTRYPOINT ["/entrypoint.sh"]

CMD ["/usr/sbin/crond", "-f"]

SHELL ["/bin/ash"]
