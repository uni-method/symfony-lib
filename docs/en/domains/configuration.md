# Configuration

## Bundle parameters

The bundle supports **several independent APIs** (multi-API). Each API has
its own name, its own config file and its own route prefix. Create
`config/packages/symfony_lib.yaml`:

```yaml
symfony_lib:
    apis:
        main:
            config_path: '%kernel.project_dir%/config/api.yaml'
            prefix: '/api'
```

`config_path` and `prefix` are required. The API name (`main`) is arbitrary and is used
in internal service and route names. Several APIs can be declared — each with
its own config and prefix.

The services of each API are named `symfony_lib.<kind>.<API name>` (`symfony_lib.config.main`,
`symfony_lib.api_service.main`) and are private; there are no shared services bound to one
of the APIs. If the application needs the config of a specific API, inject it explicitly:

```php
public function __construct(
    #[Autowire(service: 'symfony_lib.config.main')]
    private Config $config,
) {
}
```

The API config is read and validated when the container is built and stored in it
serialized: at runtime the file is not parsed. In debug mode the container
is rebuilt when the file changes; in prod, after editing the config,
`cache:clear` is needed.

## Routes

Create `config/routes/symfony_lib.yaml`:

```yaml
symfony_lib_apis:
    resource: .
    type: symfony_lib_multi_api
```

The `symfony_lib_multi_api` type connects a delegating loader that itself builds
routes for all declared APIs.

## The API config file

The config describes the API with two sections: `schema` (the contract — what exists) and `impl`
(the implementation — PHP classes and how to read/write fields). The format for JSON and YAML
is identical; the loader is chosen by the extension (`.yaml`/`.yml` → Yaml, otherwise JSON).

A minimal `config/api.yaml`:

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
      read: {}
      create: {}
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

Key rules (in full — in the frame documentation, `docs/en/domains/config.md`):

- each model/attribute/relation from `schema` must have a pair in `impl`, and vice versa
  (strict two-way pairing);
- each model needs an `id` attribute that is readable: a getter string or
  no getter (auto-detection of a public `id` property); `getter: false`
  is forbidden;
- `entityLocator`/`entitySaver`/`entityRemover` are aliases from the dictionaries
  `locators`/`savers`/`removers`.

## Endpoints

After the setup the API is available under the prefix. For the `user` model with the actions
read/create/update/delete/list:

- `GET /api/user` — list
- `GET /api/user/{id}` — read
- `POST /api/user` — create
- `PATCH /api/user/{id}` — update
- `DELETE /api/user/{id}` — delete

Check the registered routes:

```bash
php bin/console debug:router | grep symfony_lib
```
