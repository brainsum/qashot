# Setup

## Preface
This guide is about docker4drupal v1.2.0. The newest one, 1.3.0 is NOT COMPATIBLE with it.
See https://github.com/wodby/docker4drupal/tree/1.2.0 for the relevant d4d readme.

## Requirements
1. docker
1. docker-compose

Note: This guide was only tested on Ubuntu 16.04

## Environment
1. Clone the repo
1. Add this to the end of your settings.php:
```
$settings['file_private_path'] = '../private_files';
$config_directories['sync'] = '../config/prod';

if (getenv("DOCKER_ENVIRONMENT") == "docker4drupal") {
    $databases['default']['default'] = array(
        'database' => 'drupal',
        'username' => 'drupal',
        'password' => 'drupal',
        'prefix' => '',
        'host' => 'mariadb',
        'port' => '3306',
        'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
        'driver' => 'mysql',
    );
}
```
1. cd /path/to/cloned/repo/
1. find private_files -type d -exec chmod 2775 {} \;
1. find web/sites/default/files -type d -exec chmod 2775 {} \;
1. sudo chgrp 33 web/sites/default/files -R
1. sudo chgrp 33 private_files -R
    * Note: 33 is the UID and GID of the www-data user inside the docker container. If your host has this user with the same IDs, then you can replace the numbers with www-data.
1. Optional: If you wish to import a DB
    1. Uncomment this from the docker-compose.yml mariadb / volumes
        1. \- ./docker-runtime/mariadb-init:/docker-entrypoint-initdb.d # Place init .sql file(s) here.
    1. mkdir docker-runtime/mariadb-init
    1. Copy your dump (.sql file) to the created _docker-runtime/mariadb-init_ directory
1. To start the containers use this command from the project root (where the docker-compose.yml is located)
    1. docker-compose up -d && docker-compose ps
    1. Note: docker-compose ps is just a sanity check command, so you can see if every container could start or not
    1. Alternatively you can include the "production" overrides and run it on port 80 aith autorestart: docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

## Drupal install
1. docker-compose exec --user 33 php bash
1. composer install
1. cd web
1. Fresh install:
    1. drush site-install standard
    1. drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y
    1. drush ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
    1. drush cim -y
1. Imported DB
   1. If you copied the necessary sql file to the proper folder then you should have an imported DB at this point as _docker-compose up -d_ takes care of it.

## Accessing the site
1. In your browser type these to access the different parts of the environment:
    1. localhost:8000 => The site.
    1. localhost:8001 => PHPMyAdmin.
    1. localhost:8002 => MailHog.

## Miscellaneous
1. For more information (like importing an existing database) visit the docker4drupal site: 
    * http://docker4drupal.org/
    * NOTE: The www-data user's UID and GID is 33 in this project, not 82 like written on the docker4drupal site!
