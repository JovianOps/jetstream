<?php

declare(strict_types=1);

/**
 * @file
 * Site settings. Reads .env, detects the environment with Beacon, then loads
 * the matching overrides.
 *
 * Detection lives in jovian/beacon (composer dependency): platform, stack and
 * tier from environment signals, no static caches. A pre-set ENVIRONMENT_TYPE
 * always wins, which doubles as the manual override.
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

$detected = class_exists(\Jovian\Beacon\Beacon::class)
  ? (new \Jovian\Beacon\Beacon())->detect()
  : NULL;

$settings['jetstream_env'] = $detected?->type ?? (getenv('DRUPAL_ENV') ?: 'local');
$platform = $detected?->platform;

if ($settings['jetstream_env'] === 'local' && file_exists(__DIR__ . '/includes/settings.local.php')) {
  include __DIR__ . '/includes/settings.local.php';
}

if ($platform === 'lagoon' && file_exists(__DIR__ . '/includes/settings.lagoon.php')) {
  include __DIR__ . '/includes/settings.lagoon.php';
}
