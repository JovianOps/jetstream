<?php

declare(strict_types=1);

/**
 * @file
 * Exercise template/bin scripts against live local endpoints.
 *
 * Needs 127.0.0.1:8767 free; the test starts its own PHP server there.
 *
 * Run with: php tests/bin_test.php
 */

$failures = [];

$check = static function (string $description, bool $condition) use (&$failures): void {
  if (!$condition) {
    $failures[] = $description;
  }
};

$bin = __DIR__ . '/../template/bin';
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jetstream-bin-test-' . getmypid();
removeTree($sandbox);
mkdir($sandbox . '/server', 0777, TRUE);
mkdir($sandbox . '/work', 0777, TRUE);

$sql = "-- Jetstream test dump\nCREATE TABLE t (id INT);\nINSERT INTO t VALUES (1);\n";
file_put_contents($sandbox . '/server/dump.sql', $sql);
file_put_contents($sandbox . '/server/notify.php', '<?php file_put_contents(__DIR__ . "/captured.json", file_get_contents("php://input")); http_response_code(201); echo "ok";');
file_put_contents($sandbox . '/server/junk.txt', 'this is not sql');

// Fetch from a local file.
exec(sprintf('%s %s --source=file --from=%s --dest=%s 2>&1', PHP_BINARY, escapeshellarg($bin . '/fetch-db.php'), escapeshellarg($sandbox . '/server/dump.sql'), escapeshellarg($sandbox . '/work/db.sql')), $o1, $c1);
$check('File fetch exits zero.', $c1 === 0);
$check('File fetch stores the dump.', (@file_get_contents($sandbox . '/work/db.sql') ?: '') === $sql);

// Fetch from a URL served by the PHP built-in server.
$server = startServer($sandbox . '/server');
exec(sprintf('%s %s --source=url --from=%s --dest=%s 2>&1', PHP_BINARY, escapeshellarg($bin . '/fetch-db.php'), escapeshellarg('http://127.0.0.1:8767/dump.sql'), escapeshellarg($sandbox . '/work/db-url.sql')), $o2, $c2);
$check('URL fetch exits zero.', $c2 === 0);
$check('URL fetch stores the dump.', (@file_get_contents($sandbox . '/work/db-url.sql') ?: '') === $sql);

// Garbage in, refusal out.
exec(sprintf('%s %s --source=file --from=%s --dest=%s 2>&1', PHP_BINARY, escapeshellarg($bin . '/fetch-db.php'), escapeshellarg($sandbox . '/server/junk.txt'), escapeshellarg($sandbox . '/work/junk.sql')), $o3, $c3);
$check('Non-SQL content is refused.', $c3 !== 0 && !is_file($sandbox . '/work/junk.sql'));

// Notify posts JSON the endpoint accepts.
putenv('JETSTREAM_PROJECT=demo');
exec(sprintf('%s %s --webhook=%s --status=success --message=ship-it 2>&1', PHP_BINARY, escapeshellarg($bin . '/notify.php'), escapeshellarg('http://127.0.0.1:8767/notify.php')), $o4, $c4);
$captured = json_decode((string) @file_get_contents($sandbox . '/server/captured.json'), TRUE);
$check('Notify exits zero on 2xx.', $c4 === 0);
$check('Notify posts project, status and message.', ($captured['project'] ?? '') === 'demo' && ($captured['status'] ?? '') === 'success' && ($captured['message'] ?? '') === 'ship-it');

// Notify fails loudly when the endpoint rejects.
file_put_contents($sandbox . '/server/deny.php', '<?php http_response_code(500); echo "no";');
exec(sprintf('%s %s --webhook=%s 2>&1', PHP_BINARY, escapeshellarg($bin . '/notify.php'), escapeshellarg('http://127.0.0.1:8767/deny.php')), $o5, $c5);
$check('Notify fails on 5xx.', $c5 !== 0);

stopServer($server);
removeTree($sandbox);

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo "OK: fetch-db and notify work against live endpoints.\n";

/**
 * Start the PHP built-in server in the background.
 *
 * @return array{handle: mixed, pipes: array}
 */
function startServer(string $docroot): array {
  $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
  $descriptor = [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']];
  $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8767', '-t', $docroot], $descriptor, $pipes);

  if (!is_resource($process)) {
    echo "FAIL\n - Could not start the test server.\n";
    exit(1);
  }

  // Wait until the port answers.
  for ($i = 0; $i < 50; $i++) {
    $context = stream_context_create(['http' => ['timeout' => 1]]);
    if (@file_get_contents('http://127.0.0.1:8767/dump.sql', FALSE, $context) !== FALSE) {
      return ['handle' => $process, 'pipes' => $pipes];
    }
    usleep(100000);
  }

  echo "FAIL\n - The test server never answered.\n";
  exit(1);
}

function stopServer(array $server): void {
  proc_terminate($server['handle']);
  proc_close($server['handle']);
}

function removeTree(string $dir): void {
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
