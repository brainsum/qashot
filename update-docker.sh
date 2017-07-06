#!/usr/bin/env sh
# Update code for dev instances using a local environment.
git pull \
    && composer install \
    && docker-compose exec --user 33 php sh -c \
        "cd web && drush updb -y && drush cim -y && drush entity-updates -y" \
        && echo "Update successful!" \
        || echo "Update failed!"