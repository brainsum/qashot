#!/usr/bin/env bash

docker-compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.extend-prod.yml up -d --remove-orphans
