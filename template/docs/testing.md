# Testing

Three layers, each with one command:

1. **Smoke** (CI + local): `composer test` prints the Drupal version from a fresh install. If the install is broken, this fails first.
2. **Config**: the build installs with `--existing-config`, so `config/default` is asserted on every build. Export with `drush config:export` after changing config in the UI.
3. **Your tests**: add PHPUnit suites under `web/modules/custom/*/tests/src/Unit` and run them with `docker compose exec web ./vendor/bin/phpunit`. Add Behat when a browser story needs it — not before.
