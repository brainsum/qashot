#!/usr/bin/env bash
if pgrep "docker" > /dev/null
then
    echo "Docker already running."
else
    sudo service docker start || exit 1
fi

# Stop apache so it doesn't interfere with the docker containers.
if pgrep "apache2" > /dev/null
then
    sudo service apache2 stop || exit 1
else
    echo "Apache2 already stopped."
fi
# Start amazee stack.
# pygmy up || exit 1
# Start our drupal instance, ps to check if it started and open it.
docker-compose up -d || exit 1
docker-compose ps || exit 1
# docker-compose exec --user drupal drupal bash || exit 1
docker-compose exec --user 33 php bash || exit 1
