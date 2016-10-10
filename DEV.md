# Amazee.io docker info
* The user drupal (3201) is the one who runs the scripts, not www-data
* @todo: At startup, add this to path:
    * /var/www/drupal/.nvm/versions/node/v6.3.0/bin
    * This is needed for BackstopJS
* @todo: At startap, add a symlink
    * ln -s /usr/bin/python2.7 /usr/bin/python
    * This is needed for CasperJS, which is needed for BackstopJS

# Cheatsheet

[Pygmy](https://docs.amazee.io/local_docker_development/pygmy.html)
* pygmy up
    * Starts every dependency container.
* pygmy addkey \[~/.ssh/id_rsa\]
    * Add additional ssh-key.
* pygmy status
    * Check status of pygmy-container.
    
Docker compose
* docker-compose exec --user drupal drupal bash
    * Start interactive shell for the container.