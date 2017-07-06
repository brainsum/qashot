#!/bin/sh
#cd "${0%/*}" && /usr/local/bin/docker-compose exec --user 33 php sh -c "cd web && drush core-cron"

# Add to crontab like this:
# ( crontab -l ; echo "0 2 * * 1 /bin/sh ~/www/weekly-cron.sh" ) | crontab -
# Run at 2 a.m every week.
