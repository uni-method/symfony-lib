# UniMethod Symfony Bundle — документация

- [Философия](philosophy.md) — что делает бандл, принципы.
- [Разработка](development.md) — для контрибьютора: проверки, устройство тестов.

## Домены

- [Конфигурация](domains/configuration.md) — параметры бандла, роуты, файл API-конфига, эндпоинты.
- [Doctrine](domains/doctrine.md) — готовые locator/saver/remover, репозитории list-действий.
- [Security](domains/security.md) — провайдер пользователя, роли, 401/403.
- [Ограничения](domains/constraints.md) — общие констрейнты и свои.
- [Rate limit](domains/rate-limit.md) — правила, лимитер по умолчанию, своя реализация.
- [Ошибки](domains/errors.md) — исключения бандла, наблюдение за доменными.
- [Проверка конфига](domains/config-validation.md) — команда и CI.
- [Кастомизация](domains/customization.md) — свои locator/saver/remover, мутаторы.

## Использование

- [Быстрый старт](usage/quickstart.md) — от установки до первых запросов.

## Документация фреймворка

Каталог `docs/ru/` пакета frame (пути ниже — внутри него), оглавление в
`docs/ru/README.md`: конфиг (`docs/ru/domains/config.md`), модели и entity-классы
(`docs/ru/domains/models.md`), действия (`docs/ru/domains/actions.md`), list
(`docs/ru/domains/list.md`, `docs/ru/usage/list-action.md`), persistence
(`docs/ru/domains/persistence.md`), мутаторы (`docs/ru/domains/mutators.md`),
ограничения (`docs/ru/domains/constraints.md`, `docs/ru/usage/validation-patterns.md`),
ошибки (`docs/ru/domains/errors.md`).
