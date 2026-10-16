# CI

One workflow: `.github/workflows/ci.yml` with three jobs.

- `lint`: `composer validate`, `php -l` on project PHP, `docker compose config`.
- `build`: starts the stack, installs dependencies, installs the site from existing config, runs the smoke test, curls the homepage.
- `release`: on `v*` tags only, packs a tarball and attaches it to the GitHub release.

No matrix, no sharding, no DB-image cache. Add parallelism when the suite — not the install — exceeds 10 minutes.
