#!/usr/bin/env sh
# Update code for dev instances using a local environment.
git pull --recurse-submodules=yes \
    && git submodule update --recursive --remote \
    && composer install \
    && cd web \
    && drush updb -y \
    && drush cim -y \
    && drush entity-updates -y
