<?php

/**
 * @file
 * Dev settings.
 */

$settings['twig_debug'] = TRUE;

$settings['container_yamls'][] = __DIR__ . '/../development.services.yml';

// Expiration of cached pages to 0.
$config['system.performance']['cache']['page']['max_age'] = 0;
// Aggregate CSS files on.
$config['system.performance']['css']['preprocess'] = FALSE;
// Aggregate JavaScript files on.
$config['system.performance']['js']['preprocess'] = FALSE;
// Show all error messages on the site.
$config['system.logging']['error_level'] = 'all';
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$config['config_split.config_split.development']['status'] = TRUE;
$config['qa_shot.settings']['current_environment'] = 'development';
