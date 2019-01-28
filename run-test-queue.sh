#!/bin/sh

RUNTIME=$(date "+%Y-%m-%d_%H-%M-%S")

SCRIPT=$(readlink -f "$0")
SCRIPT_PATH=$(dirname "$SCRIPT")

cd "${SCRIPT_PATH}" && /usr/local/bin/docker-compose exec -T php sh -c "cd web && drush php:script modules/custom/qa_shot/tools/RunRemoteQueues 2>&1 | tee /var/www/html/private_files/logs/remote/${RUNTIME}.txt"
cd "${SCRIPT_PATH}" && /usr/local/bin/docker-compose exec -T php sh -c "cd web && drush php:script modules/custom/qa_shot/tools/RunQAShotQueues 2>&1 | tee /var/www/html/private_files/logs/local/${RUNTIME}.txt"

## Crontab should be something like this:
#
#COMPOSE_INTERACTIVE_NO_CLI=1
#
#@reboot sudo -u ubuntu /bin/sh -c "cd ~/qashot && ./prod-startup.sh"
## Run queues every minute.
#* * * * * sudo -u alpine-www-data /bin/sh ~/qashot/run-test-queue.sh
#
