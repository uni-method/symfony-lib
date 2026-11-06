# Integration with Doctrine

The bundle provides ready-made implementations of the persistence interfaces (they are registered as
services and used by aliases in the config):

- `UniMethod\SymfonyLib\Integration\DoctrineLocator` — lookup by id;
- `UniMethod\SymfonyLib\Integration\DoctrineSaver` — saving;
- `UniMethod\SymfonyLib\Integration\DoctrineRemover` — deletion.

If the model class is not served by any entity manager (not mapped in
Doctrine), they throw `EntityManagerNotFoundException` — a configuration error.

References (`reference`) and list filters by relation load the target model with its
locator: `impl.models.<model>.entityLocator`, the read action's locator or
`impl.defaultLocator` (see the frame documentation, `docs/en/domains/models.md`,
"Model locator"). If all models are Doctrine entities, one line is enough:

```yaml
impl:
  defaultLocator: doctrine
  locators:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineLocator'
```

For list actions the repository is written by you and implements
`UniMethod\Frame\Persistence\EntityCollectionInterface` (see the frame documentation,
`docs/en/domains/list.md` and `docs/en/usage/list-action.md`). `list()` receives `array $included` as its last argument —
a flat list of relation paths that will get into the response; eager
loading is done based on it.

The bundle finds repositories by the `symfony_lib.repository.collection` tag. A service with
`autoconfigure: true` gets it automatically; without autoconfigure the tag
is specified explicitly:

```yaml
# config/services.yaml
services:
  App\Repository\PostRepository:
    autoconfigure: false
    tags: ['symfony_lib.repository.collection']
```
