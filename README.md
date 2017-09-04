# QAShot

[![Build Status](https://travis-ci.org/brainsum/qashot.svg?branch=master)](https://travis-ci.org/brainsum/qashot)

QAShot is a Drupal 8 application which provides a UI to use BackstopJS to execute A/B testing of 2 sites.

Features:
* Test queue; Tests are put into a queue and are executed as soon as possible.
* UI to manage BackstopJS test cases.

## Disclaimer

QAShot is heavily in development. Breaking changes with no automatic upgrade paths might be introduced to it without notice. Use or update it at your own discretion.

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

## Installation

The UI part is a standard Drupal installation. Drupal dependencies are managed with composer.

To import the existing configuration, you need to set the site uuid to the exported one:
`drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`

You also need to remove shortcuts from the fresh install, as the standard profile creates some by default:
`drush ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'`    

To run the tests, you need [BackstopJS 2.0](https://github.com/garris/BackstopJS "BackstopJS Repository") installed globally.

Detailed install guide is in the [INSTALL.md](/INSTALL.md)

## API

At install this project sets automatically a RestApi. For more information how to use it see [API.md](/API.md) file.
