<?php

declare(strict_types=1);

/**
 * @file
 * Lagoon hosting overrides. Only installed with hosting=lagoon.
 */

if (getenv('LAGOON_PROJECT')) {
  $settings['lagoon_project'] = getenv('LAGOON_PROJECT');
}

// Lagoon provides database credentials; prefer them when present.
foreach (['MARIADB_HOST', 'MARIADB_DATABASE', 'MARIADB_USERNAME', 'MARIADB_PASSWORD'] as $key) {
  if (getenv($key)) {
    $databases['default']['default'][strtolower(str_replace('MARIADB_', '', $key))] = getenv($key);
  }
}
