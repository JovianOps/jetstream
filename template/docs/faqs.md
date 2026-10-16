# FAQs

**Why FrankenPHP instead of nginx + php-fpm?**
One container instead of two, Caddy instead of nginx config, same Drupal. If your hoster mandates nginx, swap the `web` service — `composer.json` scripts only call `drush` inside it.

**Where is Redis/Solr/ClamAV/Chrome?**
Deliberately absent. Redis is one profile away (`docker compose --profile redis up -d` + `DRUPAL_REDIS_HOST=redis`). Add search, antivirus, or browser testing when the project needs them, not on day one.

**Where is the theme build (npm, Storybook)?**
Absent until you build a custom theme with components. The starter theme is plain CSS via libraries.

**How do I get template updates?**
See `docs/updating.md`.
