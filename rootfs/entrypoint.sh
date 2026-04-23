#!/bin/ash

set -eu

log() {
  level="$1"
  shift
  printf '%s [%s] [entrypoint] %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$level" "$*"
}

log "INFO" "startup $0"

if [ -n "${SCHEDULE:-}" ] || [ -n "${CRON_CMD:-}" ]; then
  if [ -z "${SCHEDULE:-}" ] || [ -z "${CRON_CMD:-}" ]; then
    log "ERROR" "SCHEDULE and CRON_CMD must be set together" >&2
    exit 1
  fi

  log "INFO" "configure cron schedule=${SCHEDULE} command=${CRON_CMD}"
  printf '%s\n' "@reboot ${CRON_CMD}" "${SCHEDULE} ${CRON_CMD}" > /etc/crontabs/root
fi

log "INFO" "run: $*"
exec "$@"
