<?php

/**
 * @file
 * Drush rc file.
 */

if (getenv('AMAZEEIO_BASE_URL')) {
  $options['uri'] = getenv('AMAZEEIO_BASE_URL');
}

if (getenv("DOCKER_ENVIRONMENT") === 'docker4drupal') {
  $options['uri'] = 'http://localhost:8000';
}
