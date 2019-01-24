# Docker for QAShot
## General

Docker images for the QAShot project.

Current build environment:
- Docker version 18.09.1, build 4c52b90
- docker-compose version 1.23.2, build 1110ad0 (installed via pip3)

## Images
### drupal-php

Docker hub: https://hub.docker.com/r/brainsum/drupal-php

A copy of wodby/drupal-php built from stretch instead of alpine.
Used as a base image.

#### Contents
@todo: Outdated.

### qashot-php

Docker hub: https://hub.docker.com/r/brainsum/qashot-php

The main image, contains BackstopJS.

#### Contents
@todo: Outdated.

|Name|||Version|
|---|---|---|---|
|php|||7.0.22|
|nodejs|||6.11.4|
|-|npm||3.10.10|
|-|BackstopJS||3.0.29|
|-|-|chromy|0.5.5|
|-|casperjs||1.1.4|
|-|slimerjs||0.10.3|
|-|phantomjs-prebuilt||2.1.1|
|composer|||1.5.2|
|-|hirak/prestissimo||0.3.7|
|-|drush/drush||8.1.15|
|google-chrome-stable|||62.0.3202.62-1|
|firefox-esr|||52.4.0|
|xvfb||||


## Build
### drupal-php
- cd <project root>
- lint
    - ```docker run --rm -i hadolint/hadolint < docker/drupal-php/Dockerfile```
- build
    - ```docker build --rm -t brainsum/drupal-php -t brainsum/drupal-php:0.1.0 docker/drupal-php```
- cleanup
    - ```docker container ps -a and manually remove stuck containers, if any.```
    - ```docker rmi $(docker images -f "dangling=true" -q)```
    - ```docker volume rm $(docker volume ls -qf dangling=true)```
- publish
    - ```docker login```
    - (optional) ```docker images```
        - get the image hash
    - (optional) ```docker tag <hash> hbrainsum/drupal-php:<tag>```
    - ```docker push brainsum/drupal-php``` or ```docker push brainsum/drupal-php:<tag>```

### qashot-php

Same, as for drupal-php, but paths and image tags replaced properly.
