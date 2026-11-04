#!/usr/bin/env bash
set -euo pipefail
umask 077
cd /var/www/siges
exec 9>/run/lock/siges-backup.lock
flock -n 9 || exit 0
if [[ -f storage/framework/down ]]; then
  echo 'Existing maintenance window: backup skipped' >&2
  exit 1
fi
queue_active=0
timer_active=0
maintenance_started=0
systemctl is-active --quiet siges-queue.service && queue_active=1
systemctl is-active --quiet siges-scheduler.timer && timer_active=1
cleanup() {
  status=$?
  trap - EXIT
  if [[ "$maintenance_started" -eq 1 ]]; then runuser -u www-data -- /usr/bin/php artisan up || status=1; fi
  if [[ "$queue_active" -eq 1 ]]; then systemctl start siges-queue.service || status=1; fi
  if [[ "$timer_active" -eq 1 ]]; then systemctl start siges-scheduler.timer || status=1; fi
  exit "$status"
}
trap cleanup EXIT
runuser -u www-data -- /usr/bin/php artisan down --retry=60
maintenance_started=1
systemctl stop siges-scheduler.timer siges-scheduler.service siges-queue.service
# Allow pre-maintenance HTTP requests to finish (PHP-FPM timeout must be <=60s).
sleep 65
runuser -u www-data -- env SIGES_BACKUP_ROOT=/var/backups/siges /usr/bin/php scripts/backup-mysql.php --quiesced
