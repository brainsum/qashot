# QAShot install guide
## Docker
### Versions

Installation was tested in the following environment:

- Docker version 18.09.1, build 4c52b90
- docker-compose version 1.23.2, build 1110ad0
    - Installed via pip (Python 3.6.7, pip 18.0)

The steps were written for Linux.


### Steps

Preface notes:

- User/Group ID 33 is the web user `www-data` in the PHP container
- User/Group ID 1000 is the default user `php` in the PHP container


On the host machine:

- Clone the repo
    - `git clone git@github.com:brainsum/qashot.git`
- `cd qashot`
- `cp .env.example .env`
    - you can change the contents as needed
- Update the `settings.php` file, add:
    - Every instance
        - ```php
             $config_directories['sync'] = '../config/prod';

             $settings['file_private_path'] = '../private_files';
             $settings['file_public_path'] = 'sites/default/files';

             if (\file_exists(__DIR__ . '/services.cors.yml')) {
               $settings['container_yamls'][] = __DIR__ . '/services.cors.yml';
             }

             if (\file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'docker.settings.php')) {
               include_once __DIR__ . DIRECTORY_SEPARATOR . 'docker.settings.php';
             }

             if (\file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'local.settings.php')) {
               include_once __DIR__ . DIRECTORY_SEPARATOR . 'local.settings.php';
             }
             ```
    - Development
        - ```php
             if (\file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'development.settings.php')) {
               include_once __DIR__ . DIRECTORY_SEPARATOR . 'development.settings.php';
             }
             ```
    - Per instance
        - In `local.settings.php`:
            - ```php
                 $config['qa_shot.settings']['instance_id'] = '<a unique instance id, e.g a uuid or some alphanumeric string that let's you identify it>';
                 ```
                - E.g:
                    - ```php
                         $config['qa_shot.settings']['instance_id'] = 'mhavelant-docker-localhost-8mGPnkLwlqN7GSmIs8bd';
                         ```
            - If you intend on using remote workers:
                - ```php
                     $config['backstopjs.settings']['suite']['remote_host'] = 'http://my-qashot-worker.site';
                     ```
        - If you want to manage some settings via the env variables of `docker-compose.yml`, you can set these overrides with `\getenv('MY_VARIABLE)` (Note, ensure to pass the proper types, and check for errors)
- You might also want to set some drush options:
    - Create/Edit the `drush/local.drush.yml` file as needed
- `bash startup.sh`
    - or just execute the commands from the script manually
    - note, this command also enters the docker container


In the php container:

- `composer install`
    - note, for development we use GrumPHP which installs git hooks with absolute paths.
      This means, you have to fix them outside of the container, if you use git from there (recommended to avoid needing to deal with ssh keys, etc.).
    - By default, GrumPHP changes the `pre-commit` and `commit-msg` hooks.
- `drush si --account-pass=123 --db-url=mysql://drupal:drupal@mariadb/drupal standard -y`
    - Note: Change `--account-pass` as needed
- `drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`
- `drush ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'`
- `drush cim -y`
- `drush cim -y`
    - Note, this is not a typo. Due to a bug, `system.action.qa_shot_queue_test` doesn't find the plugin at first and the import fails. Config has to be imported twice until this is fixed.
- `drush cr`


On the host machine:

- Ensure using proper permissions
    - `sudo chown 1000:1000 . -R`
- Create the required folders
    - `mkdir -m 775 -p private_files`
    - `mkdir -m 775 -p private_files/logs/local`
    - `mkdir -m 775 -p private_files/logs/remote`
    - `mkdir -m 775 -p private_files/qa_test_data`
    - `mkdir -m 775 -p web/sites/default/files/qa_test_data`
- Fix additional permissions
    - `sudo chown 33:33 private_files web/sites/default/files -R`
- Ensure that test queues are executed automatically:
    - Note, this is optional for local sites
    - Add the following to cron (`crontab -e`)
        - `* * * * * sudo -u alpine-www-data /bin/sh ~/qashot/run-test-queue.sh > /dev/null 2>&1 || true`




-----------------
-----------------

## QAShot install guide (@outdated)

- [Prerequirements](#prerequirements)
    - [Base](#base)
    - [When using docker](#when-using-docker)
    - [Optional](#optional)
- [Install QAShot on Linux/Mac](#install-qashot-on-linuxmac)
    - [Install drupal](#install-drupal)
    - [Install screenshot maker](#install-screenshot-maker)
- [Install QAShot on Linux/Mac with docker](#install-qashot-on-linuxmac-with-docker)
    - [Install drupal](#install-drupal-1)
    - [Install screenshot maker](#install-screenshot-maker-1)
- [Install QAShot on Windows](#install-qashot-on-windows)
    - [Install drupal](#install-drupal-2)
    - [Install screenshot maker](#install-screenshot-maker-2)
- [Possible problems](#possible-problems)

## Prerequirements

### Base

- PHP 7.2 or newer
- composer ([https://getcomposer.org/download/](https://getcomposer.org/download/))
- drush ([http://docs.drush.org/en/8.x/install-alternative/](http://docs.drush.org/en/8.x/install-alternative/))
- node 6 + npm ([https://howtonode.org/how-to-install-nodejs](https://howtonode.org/how-to-install-nodejs), [https://nodejs.org/en/download/](https://nodejs.org/en/download/))

Then continue the traditionally installation with: [Linux/Mac guide](#install-qashot-on-linuxmac) and/or [Windows Guide](#install-qashot-on-windows)

### When using docker

- PHP 7.2 or newer
- composer ([https://getcomposer.org/download/](https://getcomposer.org/download/))
- docker ([https://docs.docker.com/engine/installation/](https://docs.docker.com/engine/installation/))
    - This guide assumes you installed it so it doesn't need sudo to run
- docker-compose ([https://docs.docker.com/compose/install/](https://docs.docker.com/compose/install/))

Continue with: [Linux/Mac guide with docker](#install-qashot-on-linuxmac-with-docker)

### Optional

- You will need **any** site locally with two copies of it, make sure, it's editable (by admin) and has a public page (viewable by anonymous user). This will be used to test QAShot, if the internet connection is slow/dead.
- If you prefer SlimerJS over PhantomJS or you want to have both of them you will need Firefox version 45 (current slimerjs supports firefox up to the version 52) has and XVFB also installed.
    - `sudo apt-get install firefox=45.\*`
    - `sudo apt-get install xvfb`

## Install QAShot on Linux/Mac

### Install drupal

1. Clone the repo to your favorite place:
`git clone https://github.com/brainsum/qashot qashot`
2. Enter the directory: `cd qashot`
3. Get drupal and module files
`composer install`
4. Import this site to your favorite apache+php config (i.e. XAMPP, Acquia Dev Desktop)  
**NOTE: at least PHP 7.0 required!**
Webroot: `<git-root>/web`
Create also a virtual host for it if your program didn't do that. (like: `qashot.localhost` or `qashot.lh` or `qashot.dd`)  
After you finished: `cd web`
5. Install site:
`drush si --site-name=QAShot --site-mail=qashot@test.com --account-name=admin --account-pass=123 --db-url=mysql://username:password@localhost/qashot standard`  
**NOTE**: don't forget to change the DB's user, password etc. in this command if needed! 
6. Modify settings.php (add these lines):
    1. `$config\_directories['sync'] = '../config/prod';`
    2. `$settings['file\_private\_path'] = '../private\_files';`
    3. `$settings['file\_public\_path'] = 'sites/default/files';`
7. Rewrite some stuff and import configs:
    1. `drush ev '\Drupal::entityManager()->getStorage("shortcut\_set")->load("default")->delete();'`
    2. `drush config-set  "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`
    3. `drush cim -y`
    4. `drush cr`

### Install screenshot maker

Install at least one of the following (globally):

1. BackstopJS ([https://github.com/garris/BackstopJS](https://github.com/garris/BackstopJS)) (`npm install -g backstopjs`)
2. SlimerJS ([https://slimerjs.org/download.html](https://slimerjs.org/download.html))  
This will need firefox, too: `sudo apt-get install firefox=45.\*`
As well as xvfb: `sudo apt-get install xvfb`

Check your installation, if your chosen one is able to run from anywhere, you are done, if not, you need to add your bin location to PATH variables, which probably need a system restart.

`backstop --version` or `slimerjs`

## Install QAShot on Linux/Mac with docker

**NOTE**: if your host PC doesn't have PHP7, than you need to run composer commands inside the docker!

### Install drupal

1. Clone repo to your favorite place:
`git clone https://github.com/brainsum/qashot qashot`
2. Enter the directory: `cd qashot`
3. Get drupal and module files
`composer install`
4. Set correct access and rights (you don't need this on Windows):
    1. `sudo chown 1000:1000 . -R`
        1. Note: 1000 is the UID and GID of the wodby user inside the docker container. By default, this should also be your own user UID/GID on the host.
    2. `sudo chown 1000:33 private\_files web/sites/default/files -R`
        1. Note: 33 is the UID and GID of the www-data user inside the docker container.
5. Start the docker container
`docker-compose up -d`
6. Enter to docker container
`docker-compose exec php bash`
7. Install site:
`cd web`
`drush si --site-name=QAShot --site-mail=qashot@test.com --account-name=admin --account-pass=123 --db-url=mysql://drupal:drupal@mariadb/drupal standard`  
**NOTE**: don't forget to change the DB's user, password etc. in this command if needed! 
8. Modify settings.php (add these lines):
    1. `$config\_directories['sync'] = '../config/prod';`
    2. `$settings['file\_private\_path'] = '../private\_files';`
    3. `$settings['file\_public\_path'] = 'sites/default/files';`
9. Rewrite some stuff and import configs:
    1. `drush ev '\Drupal::entityManager()->getStorage("shortcut\_set")->load("default")->delete();'`
    2. `drush config-set  "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`
    3. `drush cim -y`
    4. `drush cr`
10. Exit from docker console:
`Ctrl + d` or type `exit`
11. Visit your site at: [http://localhost:](http://localhost:8000/) [8000](http://localhost:8000/) , log in with user: admin , password: 123
12. Create a test, add it to the queue
13. Run the queue (exit the container and cd ../ from your qashot folder): `bash qashot/run-test-queue.sh`  
OR from inside the container:
`cd web`
`drush php-script modules/custom/qa\_shot/tools/RunQAShotQueues`

### Install screenshot maker

Install at least one of the following (globally) on your system:

1. BackstopJS ([https://github.com/garris/BackstopJS](https://github.com/garris/BackstopJS)) (`npm install -g backstopjs`)
2. SlimerJS ([https://slimerjs.org/download.html](https://slimerjs.org/download.html))  
This will need firefox, too: `sudo apt-get install firefox=45.\*`
As well as xvfb: `sudo apt-get install xvfb`

Check your installation, if your chosen one is able to run from anywhere, you are done, if not, you need to add your bin location to PATH variables, which probably need a system restart.

`backstop --version` or `slimerjs`

## Install QAShot on Windows

I recommend: Windows 10 with Bash on Linux on Windows + Acquia Dev Desktop/XAMPP

If you use Bash + XAMPP, drupal site install same as the Linux case.

If you use Bash + Acquia Dev Desktop, run composer from Bash, and drush from ADD (if ADD's composer doesn't work).  
Note: ADD has composer, but… sometimes it's not really want to work (at least for me, it never worked)

When you **install npm** , install **WINDOWS version**! Because XAMPP and ADD will run cmd not bash.

### Install drupal

1. Clone repo to your favorite place:
`git clone https://github.com/brainsum/qashot qashot`
2. Enter the directory: `cd qashot`
3. Get drupal and module files
`composer install`
4. Import this site to your favorite apache+php config (i.e. XAMPP, Acquia Dev Desktop)  
**NOTE: at least PHP 7.0 required!**
Webroot: `<git-root>/web`
Create also a virtual host for it if your program didn't do that. (like: `qashot.localhost` or `qashot.lh` or `qashot.dd`)  
After you finished: `cd web`
5. Install site:
`drush si --site-name=QAShot --site-mail=qashot@test.com --account-name=admin --account-pass=123 --db-url=mysql://username:password@localhost/qashot standard`  
**NOTE**: don't forget to change the DB's user, password etc. in this command if needed! 
6. Modify settings.php (add these lines):
    1. `$config\_directories['sync'] = '../config/prod';`
    2. `$settings['file\_private\_path'] = '../private\_files';`
    3. `$settings['file\_public\_path'] = 'sites/default/files';`
7. Rewrite some stuff and import configs:
    1. Choose one of these:
        1. From **bash** :
`drush ev '\Drupal::entityManager()->getStorage("shortcut\_set")->load("default")->delete();'`
        2. From **cmd** :
`drush ev "\Drupal::entityManager()->getStorage(\"shortcut\_set\")->load(\"default\")->delete();"`
    2. `drush config-set  "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y`
    3. `drush cim -y sync`
    4. `drush cr`

### Install screenshot maker

After you installed **Windows verison** from **npm** , Install at least one of the following (globally) from **cmd** :

1. BackstopJS ([https://github.com/garris/BackstopJS](https://github.com/garris/BackstopJS)) (`npm install -g backstopjs`)
2. SlimerJS ([https://slimerjs.org/download.html](https://slimerjs.org/download.html))  
This will need firefox, too: `sudo apt-get install firefox=45.\*`
As well as xvfb: `sudo apt-get install xvfb`

Check your installation, if your chosen one is able to run from anywhere, you are done, if not, you need to add your bin location to PATH variables, which probably need a system restart.

`backstop --version` or `slimerjs`

## Possible problems

1. After installation, my site is broken into small pieces! (Windows)
    1. This happens when you don't have a properly set temp directory. Go to /admin/config/media/file-system and watch what is there. Probably it will "/tmp". In windows it will be on your html directory's drive's root if you use ADD. Soo, if your web html is here and named phptest: E:\phptest than /tmp dir will be at: E:\tmp . Give 777 for it and cache rebuild, then you will be able to rock. This happens only once, if you didn't used this previously.

1. "Filename directory name or volume label syntax is incorrect" error at drush run (Windows, ADD)
    1. Long story in short, your global drush and local drush conflicts. Delete drush\* files from <gitroot>/vendor/bin . Than you will be able to run drush.

## Required settings.php overrides

* For sending tests to the remote worker and fetching results:

```
$config['qa_shot.settings']['current_environment'] = 'development';
if (isset($_ENV['PROJECT_ENVIRONMENT']) && \is_string($_ENV['PROJECT_ENVIRONMENT'])) {
  $config['qa_shot.settings']['current_environment'] = $_ENV['PROJECT_ENVIRONMENT'];
}
```
```
$config['backstopjs.settings']['suite']['remote_host'] = '<the host>';
```
```
$config['backstopjs.settings']['suite']['instance_id'] = 'my-awesome-site';
```
