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