# Contributing to Jetstream

> **Vibecoders welcome.** Human + AI-agent pairs are first-class contributors. The whole template is verifiable in minutes — see below.

## Quickstart

```powershell
php tests/install_test.php   # must print OK
php -l install.php            # every PHP file, every time
```

No Docker needed for template work. The compose stack and container build verify in CI.

## Rules

1. `declare(strict_types=1);` in every PHP file.
2. **Original work only.** Jetstream is a clean-room lean alternative inspired by existing templates — never copy code, text, or fixtures from other projects into this repo. Their licenses do not belong here.
3. Template changes go through `template/manifest.json`: every new file gets an entry with the right `managed` flag (`true` = refreshed by `--update`, `false` = project-owned after install) and `when` conditions instead of commented-out variants. No fence markers, no dead options.
4. No unreplaced `{{TOKENS}}` under any answer combination — `tests/install_test.php` asserts this. Extend the test when you add questions or conditional files.
5. Keep it lean: no new service, dependency, or docs page without a stated reason. Absence is a feature — check `template/docs/faqs.md` first.
6. Generated projects must install with the documented commands. If you cannot run Docker here, say so in the PR and let CI prove it.

## Pull request workflow

1. Fork and branch from `main`.
2. `php tests/install_test.php` green, `php -l` clean on touched files.
3. Describe what changed for template users and whether managed files need `--update` on existing projects.

## Mandatory compliance & eligibility notice

By cloning, forking, opening a pull request, submitting an issue, or otherwise interacting with this repository, **you explicitly certify and warrant that you are not an Excluded Party** as defined in Section 3 of the [LICENSE](./LICENSE) (specifically including **DrevOps**, its corporate affiliates, or **Alex Skrypnyk**). Any contribution submitted by an excluded entity or individual is legally void and prohibited.
