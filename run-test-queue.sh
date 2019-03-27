#!/bin/sh

RUNTIME=$(date "+%Y-%m-%d_%H-%M-%S")

SCRIPT=$(readlink -f "$0")
SCRIPT_PATH=$(dirname "$SCRIPT")

cd "${SCRIPT_PATH}"

while true
do
   /usr/local/bin/docker-compose exec --user 33 -T php sh -c "cd web && drush php:script modules/custom/qa_shot/tools/RunRemoteQueues 2>&1 | tee /var/www/html/private_files/logs/remote/${RUNTIME}.txt"
   /usr/local/bin/docker-compose exec --user 33 -T php sh -c "cd web && drush php:script modules/custom/qa_shot/tools/RunQAShotQueues 2>&1 | tee /var/www/html/private_files/logs/local/${RUNTIME}.txt"

   sleep 1
done

##
# Global crontab, e.g /etc/cron.d
#
# # m h dom mon dow user	command
#
## We want to restart on reboot.
#@reboot <user that's set up to use docker without sudo> /bin/sh -c "cd <path to qashot project root> && ./prod-startup.sh"
#
### Note: make sure /var/log/custom/qashot.cron.log is writable.
### Sleep for 20, so the drupal site can start.
# @reboot <user that's set up to use docker without sudo> sleep 20 && /bin/sh <path to qashot project root>/run-test-queue.sh 2>&1 | tee --append /var/log/custom/qashot.cron.log > /dev/null 2>&1 || true
