<?php

declare(strict_types=1);

/**
 * @file
 * Exercise install.php end to end in throwaway sandboxes.
 *
 * Run with: php tests/install_test.php
 */

$failures = [];

$check = static function (string $description, bool $condition) use (&$failures): void {
  if (!$condition) {
    $failures[] = $description;
  }
};

$run = static function (string $args) use (&$failures): array {
  $command = sprintf('%s %s %s 2>&1', PHP_BINARY, escapeshellarg(__DIR__ . '/../install.php'), $args);
  exec($command, $output, $code);

  if ($code !== 0) {
    $failures[] = sprintf('Installer exited %d for "%s": %s', $code, $args, implode("\n", $output));
  }

  return $output;
};

$sandbox = static function (string $name): string {
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jetstream-test-' . $name . '-' . getmypid();
  removeDir($dir);
  mkdir($dir, 0777, TRUE);

  return $dir;
};

$config = static function (array $answers) use ($sandbox): string {
  static $counter = 0;
  $dir = $sandbox('config' . (++$counter));
  $file = $dir . DIRECTORY_SEPARATOR . 'answers.json';
  file_put_contents($file, json_encode($answers));

  return $file;
};

$full = [
  'org' => 'acme',
  'site' => 'Acme Site',
  'machine' => '',
  'description' => 'Acme Drupal site',
  'admin_email' => 'admin@acme.test',
  'hosting' => 'lagoon',
  'redis' => 'y',
  'theme' => 'y',
  'db' => 'profile',
];

// Full install: conditionals on, machine derived, no tokens left.
$dest = $sandbox('full');
$run(sprintf('--dest=%s --no-interaction --config=%s', escapeshellarg($dest), escapeshellarg($config($full))));

$check('Full install renders composer.json.', is_file($dest . '/composer.json'));
$check('Machine name derives from the site.', is_file($dest . '/web/themes/custom/acme_site_theme/acme_site_theme.info.yml'));
$check('Lagoon settings install when chosen.', is_file($dest . '/web/sites/default/includes/settings.lagoon.php'));
$check('Theme installs when chosen.', is_file($dest . '/web/themes/custom/acme_site_theme/acme_site_theme.libraries.yml'));
$check('CI workflow installs.', is_file($dest . '/.github/workflows/ci.yml'));
$check('DB fetch script installs.', is_file($dest . '/bin/fetch-db.php'));
$check('Notify script installs.', is_file($dest . '/bin/notify.php'));
$check('Renovate config installs.', is_file($dest . '/renovate.json'));
$check('PHPUnit config installs.', is_file($dest . '/phpunit.xml'));
$check('Lagoon config installs when chosen.', is_file($dest . '/.lagoon.yml'));
$check('Receipt records answers.', str_contains((string) @file_get_contents($dest . '/.jetstream.json'), '"hosting": "lagoon"'));

$leftovers = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if ($file->isFile() && preg_match('/\{\{[A-Z_]+\}\}/', (string) file_get_contents($file->getPathname())) === 1) {
    $leftovers[] = $file->getPathname();
  }
}
$check('No unreplaced tokens remain.', $leftovers === []);

$composer = json_decode((string) file_get_contents($dest . '/composer.json'), TRUE);
$check('Rendered composer.json is valid JSON with the right name.', ($composer['name'] ?? '') === 'acme/acme_site');

// Minimal install: conditionals off.
$minimal = $full;
$minimal['hosting'] = 'none';
$minimal['theme'] = 'n';
$minimal['machine'] = 'tiny';

$dest2 = $sandbox('minimal');
$run(sprintf('--dest=%s --no-interaction --config=%s', escapeshellarg($dest2), escapeshellarg($config($minimal))));

$check('Lagoon settings skip when not chosen.', !is_file($dest2 . '/web/sites/default/includes/settings.lagoon.php'));
$check('Lagoon config skips when not chosen.', !is_file($dest2 . '/.lagoon.yml'));
$check('Theme skips when not chosen.', !is_dir($dest2 . '/web/themes/custom'));
$check('Base settings still install.', is_file($dest2 . '/web/sites/default/settings.php'));

// Update mode: managed files refresh, project files survive.
$dest3 = $sandbox('update');
$run(sprintf('--dest=%s --no-interaction --config=%s', escapeshellarg($dest3), escapeshellarg($config($full))));
file_put_contents($dest3 . '/composer.json', '{"tampered": true}');
file_put_contents($dest3 . '/docs/testing.md', 'OUR EDITS');
$run(sprintf('--update --dest=%s --no-interaction', escapeshellarg($dest3)));

$check('Update refreshes managed files.', str_contains((string) file_get_contents($dest3 . '/composer.json'), 'acme/acme_site'));
$check('Update preserves unmanaged files.', file_get_contents($dest3 . '/docs/testing.md') === 'OUR EDITS');

// Invalid configs fail.
$bad = $full;
$bad['admin_email'] = 'not-an-email';
$command = sprintf('%s %s --validate --config=%s 2>&1', PHP_BINARY, escapeshellarg(__DIR__ . '/../install.php'), escapeshellarg($config($bad)));
exec($command, $bad_output, $bad_code);
$check('Invalid email fails validation.', $bad_code !== 0);

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo "OK: installer installs, skips, updates and validates.\n";

/**
 * Remove a directory tree.
 */
function removeDir(string $dir): void {
  if (!is_dir($dir)) {
    return;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($iterator as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  }

  rmdir($dir);
}
