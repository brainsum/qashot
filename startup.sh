#!/usr/bin/env bash

docker-compose up -d || exit 1
docker-compose ps || exit 1
docker-compose exec php bash || exit 1
