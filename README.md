# QAShot

## Synopsis

QAShot is a Drupal 8 application which provides a UI to use BackstopJS to execute A/B testing of 2 sites.

Features:
* Test queue; Tests are put into a queue and are executed as soon as possible.
* UI to manage BackstopJS test cases.

## Docker
The project comes with a docker-compose.yml based on the Docker4Drupal environment (v1.3.0).
A custom docker image is used for the PHP container, as we needed to include BackstopJS and its dependencies.
Name: havelantmate/drupal_php:Backstop2.6.13-SlimerJS
Contents:
* BackstopJS 2.6.13
* Innophi SlimerJS 0.10.3
* CasperJS version 1.1.4 at /usr/lib/node_modules/casperjs, using phantomjs version 2.1.1
* Mozilla Firefox 45.0.2

## Cron
The tests are not run automatically, they are first put into a queue which is managed with cron.
If you want the tests to run, you have to set up a cron job on the system.

If you are using the docker environment described in this repo, just use this:
( crontab -l ; echo "* * * * * /bin/sh <path-to-project>/run-test-queue.sh" ) | crontab -

See https://www.drupal.org/node/23714 for more cron info.

# Outdated parts (some of it might still work)
## Installation

The UI part is standard Drupal installation. Dependencies are managed with composer.

To run the tests, you need [BackstopJS 2.0](https://github.com/garris/BackstopJS "BackstopJS Repository") installed globally. 

### Docker4Drupal
https://github.com/brainsum/qashot/blob/master/environment/docker4drupal/README.md
### amazee.io (Advice: Use Docker4Drupal instead)
https://github.com/brainsum/qashot/blob/master/environment/amazee/Readme.md

Background for installation inside an amazee.io container refer to this manual:
https://docs.google.com/document/d/1GUmDacF-VSw-e1HvzOzaq_Su_Cz0FszPX-_8dr53tnw/edit , then
