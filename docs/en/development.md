# Bundle development

For contributors: environment, pre-commit checks, test layout.

## Environment

PHP 8.3: the minimum supported version, PHP 8.4 syntax is not used.
Syntax compatibility is checked by `composer ci:php-lint`. frame — with patches from
the same series (see `CHANGELOG.md`).

## CI

All checks in CI mode (without modifying files) — with one command:

```shell
composer ci
```

Individually:

| Script                 | Command                                               |
| ---------------------- | ----------------------------------------------------- |
| `composer ci:phpcs`    | `phpcs`                                               |
| `composer ci:phpstan`  | `phpstan analyse -c phpstan.neon --memory-limit=2G`   |
| `composer ci:rector`   | `rector process --dry-run`                            |
| `composer ci:phpunit`  | `phpunit`                                             |
| `composer ci:php-lint` | `php -l` over `src` and `tests` in the `php:8.3-cli-alpine` image (requires Docker; not part of `composer ci`) |

## Checks

Running the tools manually, including with fixing files:

```bash
vendor/bin/phpunit
vendor/bin/phpcbf
vendor/bin/rector process
php -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon
```

Constraints are tested through the real Symfony Validator and
`SymfonyConstraintChecker` (`tests/Unit/Constraint/ConstraintTestCase`): the whole chain
attribute → groups by phase → validator → `Error`/exception.

## Test layout

- `tests/Unit` — unit tests by the `src` directories.
- `tests/Functional` — tests on a live kernel: `tests/Fixtures/App/TestKernel.php`
  with FrameworkBundle, SecurityBundle and the bundle; the kernel configuration variants
  are listed in its docblock. Requests go through `$kernel->handle()`.
- `tests/Fixtures` — the application for kernel tests (`App`) and entities for
  `ConfigChecker` (`Entity`).
- Fixtures intentionally contain unused private members and empty methods;
  dead code removal rules are disabled for them in `rector.php`.
- `tests/Unit/DocsTest` checks that `UniMethod\…` names and local links
  in the documentation exist.
