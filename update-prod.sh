#!/usr/bin/env sh
# Update code for dev instances using a local environment.
git pull --recurse-submodules=yes \
    && git submodule update --recursive --remote \
    && composer install --no-dev -o \
    && cd web \
    && drush updb -y \
    && drush cim -y \
    && drush entity:updates -y
