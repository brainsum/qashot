## Synopsis

QAShot is a Drupal 8.2 module which provides a UI to create BackstopJS configuration files.
The user can also run these tests manually or schedule them to run at given times.

## Installation

The UI part is standard Drupal installation. Dependencies are managed with composer.

To run the tests, you need [BackstopJS 2.0](https://github.com/garris/BackstopJS "BackstopJS Repository") installed globally. 

For installation inside an amazee.io container refer to this manual:
https://docs.google.com/document/d/1GUmDacF-VSw-e1HvzOzaq_Su_Cz0FszPX-_8dr53tnw/edit , then

1. composer install
2. drush site-install --db-url=mysql://$AMAZEEIO_DB_USERNAME:$AMAZEEIO_DB_PASSWORD@localhost/drupal
3. npm install -g backstopjs
4. $config_directories['sync'] = 'sites/default/config/prod';
5. drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y
6. drush cim -y

