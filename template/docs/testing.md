# Testing

Three layers, each with one command:

1. **Smoke** (CI + local): `composer test` prints the Drupal version from a fresh install. If the install is broken, this fails first.
2. **Unit**: `composer test-unit` runs the `unit` suite from `phpunit.xml` (plain PHP, no Drupal bootstrap). Add suites under `web/modules/custom/*/tests/src/Unit`.
3. **Config**: after changing config in the UI, run `drush config:export` to store it in `config/default`. `composer deploy` and `composer provision` import it (`config:import`), so the directory is the source of truth for existing environments; fresh builds install the minimal profile and then import it.
4. **Your tests**: add kernel/functional suites when the project earns them. Add Behat when a browser story needs it — not before.
