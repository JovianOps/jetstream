<?php

declare(strict_types=1);

/**
 * @file
 * Local development overrides. Never deployed.
 */

error_reporting(E_ALL);
$config['system.logging']['error_level'] = 'verbose';
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
