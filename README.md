# QAShot

[![Build Status](https://travis-ci.org/brainsum/qashot.svg?branch=master)](https://travis-ci.org/brainsum/qashot)

QAShot is a Drupal 8 application which provides a UI to use BackstopJS to execute A/B testing of 2 sites.

Features:
* Test queue; Tests are put into a queue and are executed as soon as possible.
* UI to manage BackstopJS test cases.

## Disclaimer

QAShot is heavily in development. Breaking changes with no automatic upgrade paths might be introduced to it without notice.
Use or update it at your own discretion.

## Info about a refactor (2019.02.01)

A recent refactor (Commit `a2ed30d102d`) results in the following error:
```The service "backstopjs.backstop" has a dependency on a non-existent service "qa_shot_test_worker.worker_factory".```

This has no easy solution, so a workaround is supplied:

- cd <project root>
- git pull
- git apply patches/qa_shot.services-deployment_hotifx.patch
- git apply patches/backstopjs.services-deployment_hotifx.patch
- do a release as usual
- checkout the 2 patched service files and clear caches

## Roadmap
QAShot will undergo big changes in the future.

### Remote BackstopJS execution
We are developing a [QAShot Worker](https://github.com/brainsum/qashot_worker) project. This will serve as a remote execution stack independent from, but used with this Drupal based project.
This will allow us to make the Drupal instance more lightweight and increase test execution speed.

### Full rewrite
We would like to also keep local run capabilities, but supporting both in a sustainable way is not possible. This means, a full back-end rewrite will start hopefully soon.

## Docker
For information about the docker stack, see [the README](docker/README.md) for the docker stack.

## Cron
The tests are not run automatically, they are first put into a queue which is managed with cron.
If you want the tests to run, you have to set up a cron job on the system.

Example:
```
# Into e.g /etc/cron.d/qashot-queue
#
# We want to restart on reboot.
@reboot <user that's set up to use docker without sudo> /bin/sh -c "cd <path to qashot project root> && ./prod-startup.sh"

# We want to execute queues automatically.
* * * * * <user that's set up to use docker without sudo> /bin/sh <path to qashot project root>/run-test-queue.sh 2>&1 | tee --append /var/log/custom/qashot.cron.log > /dev/null 2>&1 || true

```


```
COMPOSE_INTERACTIVE_NO_CLI=1

@reboot sudo -u ubuntu /bin/sh -c "cd ~/qashot && ./prod-startup.sh"
# Run queues every minute.
* * * * * sudo -u alpine-www-data /bin/sh ~/qashot/run-test-queue.sh > /dev/null 2>&1 || true
```

See https://www.drupal.org/node/23714 for more cron info.

## Installation

The UI part is a standard Drupal installation. Drupal dependencies are managed with composer.

To import the existing configuration, you need to set the site uuid to the exported one:
`drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`

You also need to remove shortcuts from the fresh install, as the standard profile creates some by default:
`drush ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'`    

To run the tests, you need [BackstopJS](https://github.com/garris/BackstopJS "BackstopJS Repository") installed globally.

Detailed install guide is in the [INSTALL.md](docs/INSTALL.md)

## API

Endpoints are exposed by the ```qa_shot_rest_api``` module.
For more information how to use it see [API.md](docs/API.md) file.
