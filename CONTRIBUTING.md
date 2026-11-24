# Contributing

## Workflow

Every change of its own — a feature, a fix, a refactoring, a documentation update — goes through a pull request:

1. Branch off an up-to-date `main`, one branch per change, named after it: `feature/…`, `fix/…`, `docs/…`,
   `ci/…`.
2. Commit there and push the branch.
3. Open a pull request into `main` that says what changes and why, and how it was tested.
4. Merge when the CI checks pass (lint, unit tests, coverage, the Joomla 5.4 and 6.2 integration tests), then delete
   the branch.

Nothing is committed straight to `main`. Releases are tagged on `main` (`vX.Y.Z`) after the merge; the tag must
match the version in `src/pkg_qrzlitesitemap.xml`.

## Before opening the pull request

```sh
composer lint        # PHP syntax, code style, static analysis
composer lint:html   # HTML and CSS (needs npm install)
composer test        # unit tests
```

A change of behaviour comes with tests and a line in `CHANGELOG.md`. See the *Development* section of the
[README](README.md) for the tools.
