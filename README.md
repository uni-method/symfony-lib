# UniMethod Symfony Bundle

A Symfony bundle for integrating the `UniMethod\Frame` framework with Symfony applications.
It turns a declarative config (`schema` + `impl`) into a ready REST API: routing,
mapping the request body into entities and back, constraint checks, access
control and error handling.

## Requirements

- PHP 8.3+
- Symfony 7.1+ or 8.0+
- Doctrine ORM 2.14+ or 3.0+ (for the built-in Doctrine integrations)
- `symfony/console` (for the config check command)

## Installation

```bash
composer require uni-method/symfony-lib
```

The bundle is registered by Symfony Flex automatically. If not, add it to
`config/bundles.php`:

```php
return [
    // ...
    UniMethod\SymfonyLib\SymfonyLibBundle::class => ['all' => true],
];
```

## Documentation

Table of contents — [docs/en/README.md](docs/en/README.md); quick start —
[docs/en/usage/quickstart.md](docs/en/usage/quickstart.md).

---

# UniMethod Symfony Bundle

Symfony-бандл для интеграции фреймворка `UniMethod\Frame` с Symfony-приложениями.
Превращает декларативный конфиг (`schema` + `impl`) в готовый REST API: роутинг,
маппинг тела запроса в сущности и обратно, проверку ограничений, контроль
доступа и обработку ошибок.

## Требования

- PHP 8.3+
- Symfony 7.1+ или 8.0+
- Doctrine ORM 2.14+ или 3.0+ (для встроенных Doctrine-интеграций)
- `symfony/console` (для команды проверки конфига)

## Установка

```bash
composer require uni-method/symfony-lib
```

Бандл регистрируется через Symfony Flex автоматически. Если нет — добавьте в
`config/bundles.php`:

```php
return [
    // ...
    UniMethod\SymfonyLib\SymfonyLibBundle::class => ['all' => true],
];
```

## Документация

Оглавление — [docs/ru/README.md](docs/ru/README.md); быстрый старт —
[docs/ru/usage/quickstart.md](docs/ru/usage/quickstart.md).
