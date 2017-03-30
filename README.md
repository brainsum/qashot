## Synopsis

Note: The readme is outdated.
QAShot is a Drupal 8.2 application which provides a UI to create BackstopJS configuration files.
The user can also run these tests manually or schedule them to run at given times.

## Installation

The UI part is standard Drupal installation. Dependencies are managed with composer.

To run the tests, you need [BackstopJS 2.0](https://github.com/garris/BackstopJS "BackstopJS Repository") installed globally. 

### Docker4Drupal
https://github.com/brainsum/qashot/blob/master/environment/docker4drupal/README.md
### amazee.io (Advice: Use Docker4Drupal instead)
https://github.com/brainsum/qashot/blob/master/environment/amazee/Readme.md

Background for installation inside an amazee.io container refer to this manual:
https://docs.google.com/document/d/1GUmDacF-VSw-e1HvzOzaq_Su_Cz0FszPX-_8dr53tnw/edit , then

### Cron
The tests are not run automatically, they are first put into a queue which is managed with cron.
If you want the tests to run, you have to set up a cron job on the system.

If you are using the docker environment described in this repo, just use this:
( crontab -l ; echo "* * * * * /bin/sh <path-to-project>/run-test-queue.sh" ) | crontab -

As of 2017. March 30, the Automated Cron module has been disabled.
If you are using the docker environment described in this repo, just use this:
( crontab -l ; echo "0 2 * * 1 /bin/sh ~/www/weekly-cron.sh" ) | crontab -

See https://www.drupal.org/node/23714 for more cron info.
