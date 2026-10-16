<?php

declare(strict_types=1);

/**
 * @file
 * Jetstream installer: renders template/ into a new Drupal project.
 *
 * Usage:
 *   php install.php --dest=/path/to/project
 *   php install.php --dest=/path/to/project --no-interaction --config=answers.json
 *   php install.php --update --dest=/path/to/project
 *   php install.php --validate --config=answers.json
 *
 * Fresh installs copy every manifest file whose "when" conditions match.
 * Update mode re-renders managed files only, preserving project content.
 */

const JETSTREAM_VERSION = '1.0.0';

$questions = [
  'org' => ['Organization (composer vendor)', 'jovian', '/^[a-z0-9-]+$/'],
  'site' => ['Site name', 'Jetstream Demo', NULL],
  'machine' => ['Machine name (leave empty to derive)', '', '/^[a-z0-9_]+$/'],
  'description' => ['One-line description', 'A Drupal site built on Jetstream', NULL],
  'admin_email' => ['Admin email', 'admin@example.com', '/^[^@\s]+@[^@\s]+\.[^@\s]+$/'],
  'hosting' => ['Hosting [lagoon|none]', 'none', '/^(lagoon|none)$/'],
  'redis' => ['Enable Redis object cache? [y|n]', 'n', '/^(y|n)$/'],
  'theme' => ['Include a starter theme? [y|n]', 'y', '/^(y|n)$/'],
  'db' => ['Database source [profile|file]', 'profile', '/^(profile|file)$/'],
];

main($argv, $questions);

function main(array $argv, array $questions): void {
  $options = parseOptions($argv);
  $root = __DIR__;

  if (isset($options['help'])) {
    echo "Jetstream installer " . JETSTREAM_VERSION . "\n";
    echo "  --dest=PATH          Destination directory (required).\n";
    echo "  --no-interaction     Answer from --config or .jetstream.json.\n";
    echo "  --config=FILE        JSON answers file.\n";
    echo "  --update             Re-render managed files only.\n";
    echo "  --validate           Validate a config file and exit.\n";
    exit(0);
  }

  $dest = $options['dest'] ?? '';
  $update = isset($options['update']);

  if ($dest === '') {
    fwrite(STDERR, "Missing --dest. See --help.\n");
    exit(1);
  }

  if (isset($options['validate'])) {
    $answers = loadConfig($options['config'] ?? '', $questions);
    validateAnswers($answers, $questions);
    echo "Config is valid.\n";
    exit(0);
  }

  if ($update) {
    $answers = loadReceipt($dest);
  }
  elseif (isset($options['no-interaction'])) {
    $answers = loadConfig($options['config'] ?? '', $questions);
  }
  else {
    $answers = askQuestions($questions);
  }

  $answers = normalizeAnswers($answers);
  validateAnswers($answers, $questions);

  $manifest = loadManifest($root);
  $rendered = renderTemplate($root, $dest, $manifest, $answers, $update);
  writeReceipt($dest, $answers);

  printf("%s %d file(s) into %s.\n", $update ? 'Updated' : 'Installed', $rendered, $dest);
}

/**
 * Parse CLI flags into key/value options.
 */
function parseOptions(array $argv): array {
  $options = [];

  foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--update' || $arg === '--no-interaction' || $arg === '--validate' || $arg === '--help') {
      $options[ltrim($arg, '-')] = TRUE;
      continue;
    }

    if (str_starts_with($arg, '--dest=')) {
      $options['dest'] = substr($arg, strlen('--dest='));
      continue;
    }

    if (str_starts_with($arg, '--config=')) {
      $options['config'] = substr($arg, strlen('--config='));
      continue;
    }

    fwrite(STDERR, sprintf("Unknown option: %s\n", $arg));
    exit(1);
  }

  return $options;
}

/**
 * Ask every question on STDIN.
 */
function askQuestions(array $questions): array {
  $answers = [];

  foreach ($questions as $key => [$label, $default, $pattern]) {
    $suffix = $default !== '' ? sprintf(' [%s]', $default) : '';
    fwrite(STDOUT, sprintf('%s%s: ', $label, $suffix));
    $input = trim((string) fgets(STDIN));
    $answers[$key] = $input !== '' ? $input : $default;
  }

  return $answers;
}

/**
 * Load answers from a JSON file.
 */
function loadConfig(string $file, array $questions): array {
  if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Missing or unreadable --config file.\n");
    exit(1);
  }

  $data = json_decode((string) file_get_contents($file), TRUE);

  if (!is_array($data)) {
    fwrite(STDERR, "The config file is not valid JSON.\n");
    exit(1);
  }

  $answers = [];
  foreach ($questions as $key => [$label, $default]) {
    $answers[$key] = $data[$key] ?? $default;
  }

  return $answers;
}

/**
 * Load answers from a previous install receipt.
 */
function loadReceipt(string $dest): array {
  $file = rtrim($dest, '/\\') . DIRECTORY_SEPARATOR . '.jetstream.json';

  if (!is_file($file)) {
    fwrite(STDERR, "No .jetstream.json in destination; cannot update.\n");
    exit(1);
  }

  $data = json_decode((string) file_get_contents($file), TRUE);

  if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
    fwrite(STDERR, "The receipt is corrupt; cannot update.\n");
    exit(1);
  }

  return $data['answers'];
}

/**
 * Validate answers against the question patterns.
 */
function validateAnswers(array $answers, array $questions): void {
  foreach ($questions as $key => [$label, $default, $pattern]) {
    $value = $answers[$key] ?? '';

    if (is_bool($value)) {
      $value = $value ? 'y' : 'n';
    }

    $value = (string) $value;

    if ($key !== 'machine' && $value === '') {
      fwrite(STDERR, sprintf("Answer required for %s.\n", $key));
      exit(1);
    }

    if ($pattern !== NULL && $value !== '' && preg_match($pattern, $value) !== 1) {
      fwrite(STDERR, sprintf("Invalid value for %s: %s\n", $key, $value));
      exit(1);
    }
  }
}

/**
 * Derive blanks and lowercase tokens.
 */
function normalizeAnswers(array $answers): array {
  if (($answers['machine'] ?? '') === '') {
    $slug = strtolower((string) ($answers['site'] ?? 'site'));
    $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
    $answers['machine'] = trim($slug, '_') !== '' ? trim($slug, '_') : 'site';
  }

  $answers['org'] = strtolower((string) $answers['org']);

  foreach (['redis', 'theme'] as $bool) {
    $answers[$bool] = in_array(strtolower((string) $answers[$bool]), ['y', 'yes', '1', 'true'], TRUE);
  }

  return $answers;
}

/**
 * Load and sanity-check the manifest.
 *
 * @return array{files: list<array{path: string, managed?: bool, when?: array<string, mixed>}>}
 */
function loadManifest(string $root): array {
  $file = $root . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'manifest.json';
  $data = json_decode((string) @file_get_contents($file), TRUE);

  if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
    fwrite(STDERR, "The manifest is missing or corrupt.\n");
    exit(1);
  }

  return $data;
}

/**
 * Whether a manifest entry applies to these answers.
 */
function entryApplies(array $entry, array $answers): bool {
  foreach ($entry['when'] ?? [] as $key => $expected) {
    if (($answers[$key] ?? NULL) != $expected) {
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * Copy and render every applicable file. Returns the file count.
 */
function renderTemplate(string $root, string $dest, array $manifest, array $answers, bool $update): int {
  $tokens = [
    '{{ORG}}' => (string) $answers['org'],
    '{{SITE}}' => (string) $answers['site'],
    '{{MACHINE}}' => (string) $answers['machine'],
    '{{DESCRIPTION}}' => (string) $answers['description'],
    '{{ADMIN_EMAIL}}' => (string) $answers['admin_email'],
  ];

  $count = 0;

  foreach ($manifest['files'] as $entry) {
    if (!entryApplies($entry, $answers)) {
      continue;
    }

    $managed = (bool) ($entry['managed'] ?? FALSE);
    $relative = strtr($entry['path'], $tokens);
    $target = rtrim($dest, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if ($update && !$managed) {
      continue;
    }

    if ($update && is_file($target)) {
      // Managed files are always refreshed.
    }
    elseif (is_file($target)) {
      fwrite(STDERR, sprintf("Refusing to overwrite %s on fresh install.\n", $relative));
      exit(1);
    }

    $source = $root . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);

    if (!is_file($source)) {
      fwrite(STDERR, sprintf("Template file missing: %s\n", $entry['path']));
      exit(1);
    }

    $content = strtr((string) file_get_contents($source), $tokens);

    if (preg_match('/\{\{[A-Z_]+\}\}/', $content) === 1) {
      fwrite(STDERR, sprintf("Unreplaced token in %s.\n", $relative));
      exit(1);
    }

    $dir = dirname($target);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }

    file_put_contents($target, $content);
    $count++;
  }

  return $count;
}

/**
 * Store answers for future updates.
 */
function writeReceipt(string $dest, array $answers): void {
  $receipt = [
    'jetstream' => JETSTREAM_VERSION,
    'date' => date('c'),
    'answers' => $answers,
  ];

  file_put_contents(
    rtrim($dest, '/\\') . DIRECTORY_SEPARATOR . '.jetstream.json',
    json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
  );
}
