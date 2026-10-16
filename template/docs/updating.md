# Updating Jetstream

Template files marked `managed` in `template/manifest.json` (compose file, Caddyfile, settings, CI, `.env.example`, `composer.json`) can be refreshed without touching your code:

```bash
php /path/to/jetstream/install.php --update --dest=/path/to/project
```

Update mode re-reads the answers stored in `.jetstream.json`, re-renders **managed files only**, and leaves everything else (your theme, config, docs edits) untouched. Commit, review the diff, run `composer build`.
