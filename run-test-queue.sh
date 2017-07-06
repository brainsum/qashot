#!/bin/sh
# cd into the script location. The docker-compose.yml should also be in here, otherwise it's gonna lead to an error.
#cd "${0%/*}" && /usr/local/bin/docker-compose exec --user 33 php sh -c "cd web && drush queue-run cron_run_qa_shot_test --time-limit 15"
cd "${0%/*}" && /usr/local/bin/docker-compose exec --user 33 php sh -c "cd web && drush php-script modules/custom/qa_shot/tools/RunQAShotQueues 2>> /var/www/html/private_files/queue-run-debug.txt"

# How to:
# Add this to your host's crontab:
#   * * * * * /bin/sh ~/www/qashot/run-test-queue
# Note: Replace paths with the ones on your host.
# You can use something like this:
# ( crontab -l ; echo "* * * * * /bin/sh ~/www/qashot/run-test-queue.sh" ) | crontab -
# ( crontab -l ; echo "* * * * * /bin/sh ~/www/run-test-queue.sh" ) | crontab -
