# Deployment

Jetstream deploys whatever is in `main` — there is no build artifact step because the stack builds from `composer.json` wherever it runs.

- **Lagoon** (installed with `hosting=lagoon`): add a standard Lagoon project pointing at this repo. Post-rollout, run the same commands as `composer provision`, then `drush cache:rebuild`. Routes and cron live in `.lagoon.yml`.
- **Any VPS**: copy the repo, `cp .env.example .env`, `docker compose up -d`, `composer install`, `composer build`.
- **Database**: fetch a dump with `composer db:fetch -- --source=url --from=https://example.com/db.sql.gz` (or `--source=file`), then `composer provision` imports it. Dumps land in `.data/db.sql`.
- **Notifications**: `composer notify -- --webhook=https://hooks.example.com/... --status=success` posts a deployment payload. Point it at Slack or your router; provider SDKs are out of scope.
