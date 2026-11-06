# UniMethod Bundle — Quick Start

Требуется PHP 8.3+.

## Шаг 1: Установка

```bash
composer require uni-method/symfony-lib
```

Если Flex не зарегистрировал бандл сам — добавьте в `config/bundles.php`:

```php
UniMethod\SymfonyLib\SymfonyLibBundle::class => ['all' => true],
```

Жёсткие зависимости — `symfony/framework-bundle` и `symfony/yaml`. Остальные
интеграции подключаются по наличию библиотеки, и без них часть возможностей
выключается:

| Пакет | Что даёт | Без него |
|---|---|---|
| `doctrine/orm` | `DoctrineLocator`/`DoctrineSaver`/`DoctrineRemover` | этих сервисов нет, локаторы/сейверы пишете сами |
| `symfony/validator` | `SymfonyConstraintChecker`, `Assert\*`-атрибуты, 422 | подставляется `NullConstraintChecker` — валидация не выполняется |
| `symfony/security-bundle` | `SymfonyUserProvider` | всё, где задан `access` с `authRequired` или `roles`, **запрещено** (401) |

Без security-bundle проверить пользователя нечем, поэтому доступ закрыт:
действие, `writable` и оператор фильтра с таким `access` отдают 401, путь
`includable` молча исключается из ответа. Ставьте его для любого конфига с
`access`.

## Шаг 2: Параметры бандла

`config/packages/symfony_lib.yaml`:

```yaml
symfony_lib:
    apis:
        main:
            config_path: '%kernel.project_dir%/config/api.yaml'
            prefix: '/api'
```

Бандл поддерживает несколько API — добавьте ещё ключей под `apis`, каждому свой
`config_path` и `prefix`.

## Шаг 3: Роуты

`config/routes/symfony_lib.yaml`:

```yaml
symfony_lib_apis:
    resource: .
    type: symfony_lib_multi_api
```

## Шаг 4: Файл API-конфига

`config/api.yaml` — две секции: `schema` (контракт) и `impl` (реализация). Каждая
модель/атрибут из `schema` обязана иметь пару в `impl`; у модели нужен `id` с
getter-строкой.

```yaml
schema:
  models:
    user:
      attributes:
        id:
          type: int
        email:
          type: string
  actions:
    user:
      list: {}
      read: {}
      create: {}
      update: {}
      delete: {}
impl:
  models:
    user:
      class: 'App\Entity\User'
      attributes:
        id:
          getter: getId
        email:
          getter: getEmail
          setter: setEmail
  actions:
    user:
      read:
        entityLocator: doctrine
      create:
        entitySaver: doctrine
      update:
        entityLocator: doctrine
        entitySaver: doctrine
      delete:
        entityLocator: doctrine
        entityRemover: doctrine
  locators:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineLocator'
  savers:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineSaver'
  removers:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineRemover'
```

Для list-действия добавьте в `schema.actions.user.list.config` правила
(pagination/sort/filters), а в `impl.actions.user.list.config` — `class`
(корневой класс запроса) и `repository`. Классы запроса (`{Model}ListRequest`,
Filter/Sort/Pagination) и репозиторий пишутся руками — см. документацию frame,
`docs/ru/usage/list-action.md`.

Правило фильтра, путь которого указывает на связь целиком (`{ path: user,
operators: [equal] }`), принимает id и отдаёт в DTO сущность. Для него у целевой
модели обязано быть read-действие с `entityLocator` — иначе конфиг не пройдёт
загрузку.

## Шаг 5: Проверка

```bash
php bin/console debug:router | grep symfony_lib
```

Ожидаемые роуты для модели `user`:

```
symfony_lib_api_main_user_list     GET     /api/user
symfony_lib_api_main_user_read     GET     /api/user/{id}
symfony_lib_api_main_user_create   POST    /api/user
symfony_lib_api_main_user_update   PATCH   /api/user/{id}
symfony_lib_api_main_user_delete   DELETE  /api/user/{id}
```

## Шаг 6: Запрос

```bash
# список
curl http://localhost:8000/api/user

# чтение
curl http://localhost:8000/api/user/1

# создание
curl -X POST http://localhost:8000/api/user \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com"}'

# обновление
curl -X PATCH http://localhost:8000/api/user/1 \
  -H "Content-Type: application/json" \
  -d '{"email": "new@example.com"}'

# удаление
curl -X DELETE http://localhost:8000/api/user/1
```

## Готово

Детали — в документации frame (каталог `docs/ru/` пакета, оглавление в
`docs/ru/README.md`): структура конфига — `docs/ru/domains/config.md`; entity-классы —
`docs/ru/domains/models.md`; list-классы и репозиторий — `docs/ru/usage/list-action.md`;
мутаторы — `docs/ru/domains/mutators.md`; обработка ошибок — `docs/ru/domains/errors.md`.
