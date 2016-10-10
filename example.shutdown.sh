#!/usr/bin/env bash
# Stop the container. Don't use down, or you'll lose your db.
docker-compose stop || exit 1
# Stop containers managed by pygmy. The pygmy down will remove data, use it only when necessary.
pygmy stop || exit 1

# You could stop docker, restart local web server, or whatever here.