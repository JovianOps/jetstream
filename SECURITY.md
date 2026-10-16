# Security Policy

## Supported versions

Security fixes are released for the latest `1.x` template only. Pin your update source to a major version and re-render managed files after upgrading (see `template/docs/updating.md`).

| Version | Supported |
| --- | --- |
| 1.x | Yes |

## Reporting a vulnerability

Report vulnerabilities privately. Do not open a public issue, because an issue discloses the problem before a fix is available.

Use [GitHub Security Advisories](https://github.com/JovianOps/jetstream/security/advisories/new) for this repository.

Include the affected version, the installer answers or project state needed to reproduce, and the impact you expect. A minimal reproduction (answers file + commands) is the most useful report.

You will receive an acknowledgement. If accepted, the fix and advisory are published together and release notes credit the reporter unless anonymity is requested. If declined, you will be told why.

## Scope

In scope:

- `install.php`: path handling (must never write outside `--dest`), token rendering, receipt handling, validation bypasses.
- Template defaults that weaken a generated project: credential handling in `.env.example`, the `DRUPAL_HASH_SALT` placeholder flow, file permissions, services exposed with default passwords.
- The CI workflow shipping insecure defaults.

Not vulnerabilities in this template:

- Findings in a generated project's own custom code, config, or content.
- Missing hardening that `docs/` explicitly defers (e.g. services you chose to enable).
- Reports against end-of-life Drupal core or contrib versions pinned by the generated project — update them and report upstream.
