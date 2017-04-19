<?php

/**
 * @file
 * Docker4Drupal settings.
 */

$databases['default']['default'] = array(
  'database' => 'drupal',
  'username' => 'drupal',
  'password' => 'drupal',
  'prefix' => '',
  'host' => 'mariadb',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

$base_url = 'localhost:8000';
