<?php

declare(strict_types=1);

/**
 * @file
 * Fetch a database dump into .data/db.sql for provisioning.
 *
 * Usage:
 *   php bin/fetch-db.php --source=file --from=/backups/db.sql
 *   php bin/fetch-db.php --source=url --from=https://example.com/db.sql.gz
 *
 * Sources: file (local path), url (http/https, .gz decoded automatically).
 * S3/FTP/Lagoon/Acquia remotes are intentionally out of scope: publish the
 * dump where this script can reach it instead of adding provider SDKs.
 */

main($argv);

function main(array $argv): void {
  $options = parseFetchOptions($argv);

  if (isset($options['help'])) {
    echo "Fetch a database dump into .data/db.sql.\n";
    echo "  --source=file|url    Where the dump lives (required).\n";
    echo "  --from=PATH-OR-URL   Location (required).\n";
    echo "  --dest=PATH          Target file (default .data/db.sql).\n";
    exit(0);
  }

  $source = $options['source'] ?? '';
  $from = $options['from'] ?? '';
  $dest = $options['dest'] ?? '.data/db.sql';

  if (($source !== 'file' && $source !== 'url') || $from === '') {
    fwrite(STDERR, "Usage: php bin/fetch-db.php --source=file|url --from=PATH-OR-URL [--dest=PATH]\n");
    exit(1);
  }

  $dir = dirname($dest);
  if (!is_dir($dir)) {
    mkdir($dir, 0777, TRUE);
  }

  $sql = $source === 'file' ? readLocalDump($from) : downloadDump($from);
  assertLooksLikeSql($sql, $from);

  file_put_contents($dest, $sql);
  printf("Fetched %d byte(s) into %s.\n", strlen($sql), $dest);
}

/**
 * Parse CLI flags.
 */
function parseFetchOptions(array $argv): array {
  $options = [];

  foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
      $options['help'] = TRUE;
      continue;
    }

    foreach (['--source=', '--from=', '--dest='] as $prefix) {
      if (str_starts_with($arg, $prefix)) {
        $options[rtrim(substr($prefix, 2, -1), '=')] = substr($arg, strlen($prefix));
        continue 2;
      }
    }

    fwrite(STDERR, sprintf("Unknown option: %s\n", $arg));
    exit(1);
  }

  return $options;
}

/**
 * Read a local dump file.
 */
function readLocalDump(string $path): string {
  if (!is_file($path)) {
    fwrite(STDERR, sprintf("Dump file not found: %s\n", $path));
    exit(1);
  }

  $content = @file_get_contents($path);

  if ($content === FALSE) {
    fwrite(STDERR, sprintf("Dump file unreadable: %s\n", $path));
    exit(1);
  }

  return $content;
}

/**
 * Download a dump over HTTP(S), decoding gzip when served or suffixed.
 */
function downloadDump(string $url): string {
  if (preg_match('#^https?://#i', $url) !== 1) {
    fwrite(STDERR, "URL source needs an http(s) URL.\n");
    exit(1);
  }

  $context = stream_context_create(['http' => ['timeout' => 300, 'follow_location' => 1]]);
  $content = @file_get_contents($url, FALSE, $context);

  if ($content === FALSE) {
    fwrite(STDERR, sprintf("Download failed: %s\n", $url));
    exit(1);
  }

  if (str_ends_with(strtolower($url), '.gz') || str_starts_with($content, "\x1f\x8b")) {
    $decoded = @gzdecode($content);

    if ($decoded === FALSE) {
      fwrite(STDERR, "Response claims gzip but cannot be decoded.\n");
      exit(1);
    }

    return $decoded;
  }

  return $content;
}

/**
 * Refuse to store content that is clearly not a SQL dump.
 */
function assertLooksLikeSql(string $sql, string $from): void {
  if (trim($sql) === '' || !str_contains($sql, ';')) {
    fwrite(STDERR, sprintf("Refusing to store %s: not a SQL dump.\n", $from));
    exit(1);
  }
}
