#!/usr/bin/env bash

# Build the base image first.
RUNTIME=$(date "+%Y-%m-%d_%H-%M-%S")
IMAGE=drupal-php

echo "Build running at ${RUNTIME} for ${IMAGE}.."
time docker-compose -f docker-compose.build.yml build ${IMAGE} 2>&1 | tee "docker/log/buildlog.${IMAGE}.${RUNTIME}.txt"
echo ""
echo "See debug log in 'docker/log/buildlog.${IMAGE}.${RUNTIME}.txt'"


# Then build the QAS image.
RUNTIME=$(date "+%Y-%m-%d_%H-%M-%S")
IMAGE=qashot-php

echo "Build running at ${RUNTIME} for ${IMAGE}.."
time docker-compose -f docker-compose.build.yml build ${IMAGE} 2>&1 | tee "docker/log/buildlog.${IMAGE}.${RUNTIME}.txt"
echo ""
echo "See debug log in 'docker/log/buildlog.${IMAGE}.${RUNTIME}.txt'"

# Cleanup.
docker rmi $(docker images -f "dangling=true" -q)
docker volume rm $(docker volume ls -qf dangling=true)
