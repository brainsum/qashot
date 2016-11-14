# Installation
## Requirements
* This guide is meant for Ubuntu
* Install docker and docker-compose
* Install ruby, pygmy
* Do this modification and restart your machine:
    * http://askubuntu.com/questions/233222/how-can-i-disable-the-dns-that-network-manager-uses/233223#233223
* For reference, the amazee.io docs
    * https://docs.amazee.io/step_by_step_guides/get_your_drupal_site_running_on_amazeeio.html    
    
## Process
* You can use the (UNTESTED!!!) setup.sh in the git root
    * This will copy the environment files, change permissions, etc. when run
    * NOTE: It's probably incomplete, some prerequisites might be missing
* If not using the script,
    * copy the contents of
        * root to the document root
        * settings to sites/default
    * change the private and public folder paths in the all.settings.php file to your liking
    * add a qa_shot_test folder under the public and private folders
        * this folder has to have drupal as its user and www-data as its group
        * drupal user must have rwx
    * fix file permissions for drupal
        * Amazee.io uses the user drupal (uid 3201, gid 3201) as its main user
        * and www-data (uid 33, gid 33) as its web user    
    * NOTE: This stuff is in the setup.sh, you can take a look at that as well
* If you have a webserver running on your machine, the port 443 is likely used
    * This port is needed by pygmy, so stop the webserver (apache, nginx)
* Start docker (if not already started)
* Stand in the document root and use the startup.sh
    * This will start pygmy, docker-compose and will enter the container as the drupal user
* Install BackstopJS
    * npm install -g backstopjs
* Exit the container and re-enter as root
    * Use the "exit" command
    * docker-compose exec --user root drupal bash
* Create a symlink:
    * ln -s /usr/bin/python2.7 /usr/bin/python
* Exit the container end re-enter as drupal    
    * docker-compose exec --user drupal drupal bash
* Install remaining dependencies:
    * composer install
* Do some sanity checks:
    * python --version
    * backstop --version
    * node --version
    * drush status
    
## Drupal    
* Next, install the site regularly and import the configuration
    * composer install
    * drush site-install --db-url=mysql://$AMAZEEIO_DB_USERNAME:$AMAZEEIO_DB_PASSWORD@localhost/drupal
    * Update sites/default/settings.php
        * $settings['file_private_path'] = '../private_files';
        * $config_directories['sync'] = '../config/prod';
    * drush config-set "system.site" uuid "f700763e-1289-406f-919e-98dc38728a53" -y
    * drush ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
    * drush cim -y
* If everything is ok, you can open the url in a browser:
    * qashot.docker.amazee.io
        
# Usage
* The site URL is this
    * qashot.docker.amazee.io
    * If it's not that (for some magic reasons):
        * Look at the terminal prompt
        * On the host machine use "docker-compose ps", and copy the name
        * Look at the docker-compose.yml file
* Log in with the user 1
    * Use "drush uli" for the first time login and change the password
* Go to structure in the admin toolbar
* Open the "QAShot Test List" link
* Add a new test (fill and save)
* Go back to the edit page, and press "Run test"
* Wait for the test to finish
