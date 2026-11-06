# Customization

## Your own Locator / Saver / Remover

1. Implement the required interface from `UniMethod\Frame\Persistence`
   (`EntityLocatorInterface`, `EntitySaverInterface`, `EntityRemoverInterface`).
2. Register the class as a service (it does not have to be public). If
   the service is not in the container or does not implement the interface, the container build
   fails with `ImplServiceNotFoundException` / `ImplServiceTypeMismatchException`.
3. Add an alias to the corresponding config dictionary:

```yaml
impl:
  savers:
    my_custom: 'App\Api\Saver\MyCustomSaver'
```

The locator answers only "find by id"; the check "is the current
user allowed this object" is a constraint with action groups (see [constraints.md](constraints.md)),
not a custom locator.

## Mutators

1. Implement `AttributeMutatorInterface` or `RelationMutatorInterface`
   (`UniMethod\Frame\Mutator`).
2. Register it as a service (it is checked the same way as for
   locator/saver/remover).
3. Declare it in the config: the `impl.mutators`/`impl.relationMutators` dictionary and the binding
   by phase in `schema`.
