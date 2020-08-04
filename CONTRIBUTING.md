# Contributing guide

## Introducing new code

All contributed code must be introduced via a merge/pull request and must be reviewed.

## Codestyle

All committed code must follow the standards as defined by:

- `.editorconfig`
- `.php_cs`

This can be achieved via:

- `composer run-script style-check` To check if the code matches the guidelines
- `composer run-script style-fix` To modify the code to match the guidelines

## Tests

If contributed code includes testcases, all tests must pass. Contributed code may not cause test failures of existing tests.

To run the tests, use:

- `composer run-script test` or,
- `vendor/bin/phpunit`
