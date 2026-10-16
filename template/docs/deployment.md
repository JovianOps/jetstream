# Deployment

Jetstream deploys whatever is in `main` — there is no build artifact step because the stack builds from `composer.json` wherever it runs.

- **Lagoon** (installed with `hosting=lagoon`): add a standard Lagoon project pointing at this repo. Post-rollout, run the same commands as `composer provision`, then `drush cache:rebuild`.
- **Any VPS**: copy the repo, `cp .env.example .env`, `docker compose up -d`, `composer install`, `composer build`.
- **Database**: ship `.data/db.sql` with the project (gitignored by default — store it where your team agrees) and `composer provision` imports it, or install fresh from config.

Protect `main`, deploy tags. That is the whole strategy.
