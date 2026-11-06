# Разработка бандла

Для контрибьютора: окружение, проверки перед коммитом, устройство тестов.

## Окружение

PHP 8.3: минимальная поддерживаемая версия, синтаксис PHP 8.4 не используется.
Совместимость синтаксиса проверяет `composer ci:php-lint`. frame — с патчами из
той же серии (см. `CHANGELOG.md`).

## CI

Все проверки в режиме CI (без изменения файлов) — одной командой:

```shell
composer ci
```

По отдельности:

| Скрипт                 | Команда                                               |
| ---------------------- | ----------------------------------------------------- |
| `composer ci:phpcs`    | `phpcs`                                               |
| `composer ci:phpstan`  | `phpstan analyse -c phpstan.neon --memory-limit=2G`   |
| `composer ci:rector`   | `rector process --dry-run`                            |
| `composer ci:phpunit`  | `phpunit`                                             |
| `composer ci:php-lint` | `php -l` по `src` и `tests` в образе `php:8.3-cli-alpine` (нужен Docker; в `composer ci` не входит) |

## Проверки

Ручной запуск инструментов, в том числе с исправлением файлов:

```bash
vendor/bin/phpunit
vendor/bin/phpcbf
vendor/bin/rector process
php -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon
```

Констрейнты тестируются через реальный Symfony Validator и
`SymfonyConstraintChecker` (`tests/Unit/Constraint/ConstraintTestCase`): связка
атрибут → группы по фазе → валидатор → `Error`/исключение целиком.

## Устройство тестов

- `tests/Unit` — модульные тесты по каталогам `src`.
- `tests/Functional` — тесты на живом ядре: `tests/Fixtures/App/TestKernel.php`
  с FrameworkBundle, SecurityBundle и бандлом; варианты конфигурации ядра
  перечислены в его docblock. Запросы идут через `$kernel->handle()`.
- `tests/Fixtures` — приложение для kernel-тестов (`App`) и сущности для
  `ConfigChecker` (`Entity`).
- Фикстуры намеренно содержат неиспользуемые приватные члены и пустые методы;
  в `rector.php` для них отключены правила удаления мёртвого кода.
- `tests/Unit/DocsTest` проверяет, что имена `UniMethod\…` и локальные ссылки
  в документации существуют.
