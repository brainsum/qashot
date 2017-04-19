#!/usr/bin/env sh
# Update code for dev instances using a local environment.
git pull --recurse-submodules=yes \
    && git submodule update --recursive --remote \
    && composer install --no-dev -o \
    && docker-compose exec --user 82 php sh -c \
        "cd web && drush updb -y && drush cim -y && drush entity-updates -y" \
        && echo "Update successful!" \
        || echo "Update failed!"
