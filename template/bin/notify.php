<?php

declare(strict_types=1);

/**
 * @file
 * Post a deployment notification to a generic webhook (Slack-compatible).
 *
 * Usage:
 *   php bin/notify.php --webhook=https://hooks.example.com/... --status=success
 *
 * Reads JETSTREAM_PROJECT / JETSTREAM_ENVIRONMENT / GITHUB_SHA from the
 * environment when present. Exits non-zero when the webhook rejects the call.
 * Provider-specific notifiers (email, Jira, New Relic) are out of scope: point
 * this at your router instead of adding SDKs.
 */

main($argv);

function main(array $argv): void {
  $options = parseNotifyOptions($argv);

  if (isset($options['help'])) {
    echo "Post a deployment notification to a webhook.\n";
    echo "  --webhook=URL        Webhook endpoint (required).\n";
    echo "  --status=TEXT        e.g. success, failure (default success).\n";
    echo "  --message=TEXT       Extra context (optional).\n";
    exit(0);
  }

  $webhook = $options['webhook'] ?? '';
  $status = $options['status'] ?? 'success';

  if (!preg_match('#^https?://#i', $webhook)) {
    fwrite(STDERR, "A --webhook http(s) URL is required.\n");
    exit(1);
  }

  $payload = json_encode([
    'project' => getenv('JETSTREAM_PROJECT') ?: 'unknown',
    'environment' => getenv('JETSTREAM_ENVIRONMENT') ?: 'unknown',
    'status' => $status,
    'sha' => getenv('GITHUB_SHA') ?: '',
    'message' => $options['message'] ?? '',
  ]);

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => ['Content-Type: application/json'],
      'content' => $payload,
      'timeout' => 30,
      'ignore_errors' => TRUE,
    ],
  ]);

  $response = @file_get_contents($webhook, FALSE, $context);

  if ($response === FALSE) {
    fwrite(STDERR, "Webhook unreachable.\n");
    exit(1);
  }

  $code = 0;
  foreach ($http_response_header ?? [] as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
      $code = (int) $m[1];
    }
  }

  if ($code < 200 || $code >= 300) {
    fwrite(STDERR, sprintf("Webhook rejected the notification (HTTP %d).\n", $code));
    exit(1);
  }

  echo "Notified.\n";
}

/**
 * Parse CLI flags.
 */
function parseNotifyOptions(array $argv): array {
  $options = [];

  foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help') {
      $options['help'] = TRUE;
      continue;
    }

    foreach (['--webhook=', '--status=', '--message='] as $prefix) {
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
