#!/bin/ash -e

echo "startup $0"

if [ ! -z "${SCHEDULE}" ] &&  [ ! -z "${CRON_CMD}" ]; then
  echo "configure cron: ${SCHEDULE} ${CRON_CMD}"
  echo "@reboot ${CRON_CMD}" > /etc/crontabs/root
  echo "${SCHEDULE} ${CRON_CMD}" >> /etc/crontabs/root
fi

echo "run: $@"
exec "$@"
