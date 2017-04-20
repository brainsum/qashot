<?php

/**
 * @file
 * Drush rc file.
 */

if (getenv('AMAZEEIO_BASE_URL')) {
  $options['uri'] = getenv('AMAZEEIO_BASE_URL');
}
