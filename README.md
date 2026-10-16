# Jetstream

A lean Drupal 11 project template. Nine questions, three containers, one CI workflow — then it gets out of your way.

```bash
git clone https://github.com/JovianOps/jetstream.git my-site
php jetstream/install.php --dest=my-site
cd my-site && cp .env.example .env && docker compose up -d
composer install && composer build
```

Non-interactive: `php install.php --dest=site --no-interaction --config=answers.json` (see `install.php --help`). Refresh managed files later: `php install.php --update --dest=site`.

## Why another template

Vortex (`drevops/vortex`) is the reference: ~50-prompt installer, Ahoy + 44 Bash scripts, dual CI, 8-service Compose with fence-stripped variants, Docusaurus docs. It is powerful and heavy. Jetstream makes the opposite trades, all original code:

| Vortex | Jetstream |
| :--- | :--- |
| ~50-prompt Symfony installer + snapshots | `install.php`, 9 questions, manifest-driven, no fences |
| Ahoy + 44 `vortex-*` Bash scripts + BATS | `composer run` scripts + 2 tested PHP CLIs (`fetch-db`, `notify`) |
| GHA + CircleCI, DB caches, Behat sharding | One GHA workflow: lint → build → release |
| 8 Dockerfiles, 8 services, `###` CI rewrites | 2 services (`web` FrankenPHP + `db`), Redis as a compose profile, minimal Lagoon file on request |
| Notify matrix (email/slack/jira/…) × providers | One generic webhook notifier, proven against a live endpoint |
| DB fetch from 6 sources | DB fetch from file/URL with gzip + SQL sanity gate, proven against a live server |
| Docusaurus site + videos + prose lint | 6 markdown files |

Deliberately absent until needed: Solr, ClamAV, browser testing, Storybook, npm theme builds. Redis is one profile away.

## Layout

- `install.php` — the installer (interactive, `--config`, `--update`, `--validate`).
- `template/` — the project template; `manifest.json` lists every file, its `managed` flag (refreshed by `--update`), and `when` conditions.
- `tests/install_test.php` — end-to-end installer test (install, skip, update, validate).

## Verification status (honest)

- ✅ `install.php` + manifest: proven here — `php tests/install_test.php` prints `OK: installer installs, skips, updates and validates.` Every template file renders with zero unreplaced `{{TOKENS}}` under both option sets.
- ✅ `bin/fetch-db.php` + `bin/notify.php`: proven here — `php tests/bin_test.php` prints `OK: fetch-db and notify work against live endpoints.` (local file + HTTP + gzip paths, refusal of non-SQL, webhook 2xx/5xx handling).
- ✅ All PHP lint-clean (`php -l`).
- ⏳ Docker stack, `composer build` inside containers, and the CI workflow need Docker + a Drupal install — unverified locally (no Docker on this machine). They run in CI on every push; treat them as beta until green there.

## Daily commands (inside a generated project)

`composer up|down|build|provision|test|login` — see `template/composer.json` scripts. That is the whole task runner.
