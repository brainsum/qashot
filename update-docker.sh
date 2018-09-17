#!/usr/bin/env sh
# Update code for dev instances using a local environment.
git pull \
    && docker-compose exec php sh -c \
        "composer install && cd web && drush updb -y && drush cim -y && drush entity:updates -y" \
        && echo "Update successful!" \
        || echo "Update failed!"