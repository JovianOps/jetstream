<?php

declare(strict_types=1);

/**
 * @file
 * Site settings. Reads .env, then loads the local or hosting overrides.
 */

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'insecure-default-change-me';
$settings['config_sync_directory'] = getenv('DRUPAL_CONFIG_PATH') ?: '../config/default';

$databases['default']['default'] = [
  'database' => getenv('DRUPAL_DB_NAME') ?: 'drupal',
  'username' => getenv('DRUPAL_DB_USER') ?: 'drupal',
  'password' => getenv('DRUPAL_DB_PASSWORD') ?: 'drupal',
  'host' => getenv('DRUPAL_DB_HOST') ?: 'db',
  'port' => getenv('DRUPAL_DB_PORT') ?: '3306',
  'driver' => 'mysql',
];

if (getenv('DRUPAL_REDIS_HOST')) {
  $settings['redis.connection']['host'] = getenv('DRUPAL_REDIS_HOST');
  $settings['cache']['default'] = 'cache.backend.redis';
}

$env = getenv('DRUPAL_ENV') ?: 'local';

if ($env === 'local' && file_exists(__DIR__ . '/includes/settings.local.php')) {
  include __DIR__ . '/includes/settings.local.php';
}

if (getenv('LAGOON')) {
  $lagoon_settings = __DIR__ . '/includes/settings.lagoon.php';

  if (file_exists($lagoon_settings)) {
    include $lagoon_settings;
  }
}
