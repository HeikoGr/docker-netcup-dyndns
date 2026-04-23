#!/bin/ash

set -eu

echo "startup $0"

if [ -n "${SCHEDULE:-}" ] || [ -n "${CRON_CMD:-}" ]; then
  if [ -z "${SCHEDULE:-}" ] || [ -z "${CRON_CMD:-}" ]; then
    echo "SCHEDULE and CRON_CMD must be set together" >&2
    exit 1
  fi

  echo "configure cron: ${SCHEDULE} ${CRON_CMD}"
  printf '%s\n' "@reboot ${CRON_CMD}" "${SCHEDULE} ${CRON_CMD}" > /etc/crontabs/root
fi

echo "run: $@"
exec "$@"
