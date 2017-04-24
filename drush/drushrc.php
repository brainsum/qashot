<?php

/**
 * @file
 * Drush rc file.
 */

if (getenv('AMAZEEIO_BASE_URL')) {
  $options['uri'] = getenv('AMAZEEIO_BASE_URL');
}

$localFile = __DIR__ . '/drushrc.local.php';
if (file_exists($localFile)) {
  include_once $localFile;
}
