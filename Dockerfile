FROM php:8-cli-alpine

ARG BUILD_DATE
ARG VCS_REF
ARG VERSION

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

LABEL org.opencontainers.image.created=${BUILD_DATE} \
      org.opencontainers.image.revision=${VCS_REF} \
      org.opencontainers.image.revision=${VERSION} \
      org.opencontainers.image.source="https://github.com/b2un0/docker-netcup-dyndns"
