# Releasing

1. Merge to `main` — CI must be green.
2. Tag `vX.Y.Z` — CI packs a release tarball automatically.
3. Write the release notes by hand: what changed for editors, what changed for developers, any `composer provision` steps for existing environments.

No changelog bot, no auto-generated notes until releases become routine.
