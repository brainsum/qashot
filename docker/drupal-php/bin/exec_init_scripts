#!/usr/bin/env bash

set -e

if [[ -n "${DEBUG}" ]]; then
    set -x
fi

shopt -s nullglob
for f in /docker-entrypoint-init.d/*.sh; do
    . "$f"
done
shopt -u nullglob
