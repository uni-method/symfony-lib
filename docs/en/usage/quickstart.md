# UniMethod Bundle — Quick Start

PHP 8.3+ is required.

## Step 1: Installation

```bash
composer require uni-method/symfony-lib
```

If Flex did not register the bundle itself, add it to `config/bundles.php`:

```php
UniMethod\SymfonyLib\SymfonyLibBundle::class => ['all' => true],
```

The hard dependencies are `symfony/framework-bundle` and `symfony/yaml`. Other
integrations are connected depending on whether the library is installed, and without them some of the features
are disabled:

| Package | What it gives | Without it |
|---|---|---|
| `doctrine/orm` | `DoctrineLocator`/`DoctrineSaver`/`DoctrineRemover` | these services do not exist, you write locators/savers yourself |
| `symfony/validator` | `SymfonyConstraintChecker`, `Assert\*` attributes, 422 | `NullConstraintChecker` is substituted — validation is not performed |
| `symfony/security-bundle` | `SymfonyUserProvider` | everything where `access` with `authRequired` or `roles` is set is **forbidden** (401) |

Without security-bundle there is nothing to check the user with, so access is closed:
an action, `writable` and a filter operator with such `access` return 401, an
`includable` path is silently excluded from the response. Install it for any config with
`access`.

## Step 2: Bundle parameters

`config/packages/symfony_lib.yaml`:

```yaml
symfony_lib:
    apis:
        main:
            config_path: '%kernel.project_dir%/config/api.yaml'
            prefix: '/api'
```

The bundle supports several APIs — add more keys under `apis`, each with its own
`config_path` and `prefix`.

## Step 3: Routes

`config/routes/symfony_lib.yaml`:

```yaml
symfony_lib_apis:
    resource: .
    type: symfony_lib_multi_api
```

## Step 4: The API config file

`config/api.yaml` — two sections: `schema` (the contract) and `impl` (the implementation). Each
model/attribute from `schema` must have a pair in `impl`; a model needs an `id` with
a getter string.

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

For a list action add rules (pagination/sort/filters) to `schema.actions.user.list.config`,
and to `impl.actions.user.list.config` — `class`
(the root request class) and `repository`. The request classes (`{Model}ListRequest`,
Filter/Sort/Pagination) and the repository are written by hand — see the frame documentation,
`docs/en/usage/list-action.md`.

A filter rule whose path points to a whole relation (`{ path: user,
operators: [equal] }`) accepts an id and puts the entity into the DTO. For it the target
model must have a read action with `entityLocator` — otherwise the config will not pass
loading.

## Step 5: Check

```bash
php bin/console debug:router | grep symfony_lib
```

Expected routes for the `user` model:

```
symfony_lib_api_main_user_list     GET     /api/user
symfony_lib_api_main_user_read     GET     /api/user/{id}
symfony_lib_api_main_user_create   POST    /api/user
symfony_lib_api_main_user_update   PATCH   /api/user/{id}
symfony_lib_api_main_user_delete   DELETE  /api/user/{id}
```

## Step 6: Request

```bash
# list
curl http://localhost:8000/api/user

# read
curl http://localhost:8000/api/user/1

# create
curl -X POST http://localhost:8000/api/user \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com"}'

# update
curl -X PATCH http://localhost:8000/api/user/1 \
  -H "Content-Type: application/json" \
  -d '{"email": "new@example.com"}'

# delete
curl -X DELETE http://localhost:8000/api/user/1
```

## Done

Details are in the frame documentation (the package's `docs/en/` directory, the table of contents in
`docs/en/README.md`): the config structure — `docs/en/domains/config.md`; entity classes —
`docs/en/domains/models.md`; list classes and the repository — `docs/en/usage/list-action.md`;
mutators — `docs/en/domains/mutators.md`; error handling — `docs/en/domains/errors.md`.
