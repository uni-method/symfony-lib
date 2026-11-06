# Config validation

The `symfony-lib:config:validate` command checks API configs without HTTP requests —
on the same configuration errors that would otherwise give a 500 on the first request.
It is registered if `symfony/console` is installed.

```bash
bin/console symfony-lib:config:validate        # all APIs from symfony_lib.apis
bin/console symfony-lib:config:validate main   # one API
```

The check runs on three levels; each error in the output is marked with its level:

| Label | What is checked |
|---|---|
| `[load]` | the config is loaded and passes the framework validation. The validator stops at the first error; if the config did not load, the other levels are not checked for this API |
| `[services]` | `config.repository` of each list action is a service with `EntityCollectionInterface`; `ApiService` is assembled. Services of the `impl` dictionaries are checked earlier, when the container is built |
| `[impl]` | reconciliation of `impl` with classes: the model class exists; getters and setters are public methods or public properties; the constructor of created models matches `constructorParam`; models under the Doctrine locator/saver/remover are mapped in Doctrine; reference relations do not lead to models without a read action with a locator; `OwnedByCurrentUser`/`ScopedToCurrentUser` paths are readable on the classes |

Exit codes: `0` — no errors, `1` — at least one error found, `2` —
unknown API name.

Example output:

```text
API 'main'
----------

 * [services] List action of model 'post': repository App\Repository\PostRepository is not a service implementing UniMethod\Frame\Persistence\EntityCollectionInterface
 * [impl] Model 'post' attribute 'title': getter is not set and App\Entity\Post has no public property 'title' — the value is silently omitted from responses. Set 'getter' or 'getter: false'
 * [impl] UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser on App\Entity\Post: path 'autor' — segment 'autor' is not readable on App\Entity\Post

 [ERROR] Configuration errors found
```

What is worth knowing:

- `[impl]` considers an error an attribute or relation without `getter`/`setter` and without
  a public property of the same name: at runtime such a value silently does not get
  into the response or is not written. If the omission is intentional, specify in `impl.models`
  `getter: false` or `setter: false`.
- Setters are checked only for models that are written (create/update and
  nested creates via `writable`), relations — only those listed in `writable`.
- Constraint paths are checked only for constraints declared as attributes
  (including parent classes), and by the declared types of properties and return
  values: where the type is not set, is `mixed` or a union, the path is not checked further.
  `identityPath` is checked if `userModel` is set in the config.
- A mismatch of values when mapping the response
  (`ResponseTypeMismatchException`) is not caught statically: it depends on the data.
- `[impl]` repeats the framework rules rather than calling them. If the command and
  the runtime diverge, the source of truth is the runtime.

## In CI

Run it in the environment whose config goes to deployment: the set of services and
parameters may differ between dev and prod. The command itself does not access the DB,
it needs Doctrine only for the mapping metadata.

```bash
composer install --no-interaction
APP_ENV=prod bin/console symfony-lib:config:validate
```

The step fails with code `1` if any API has a configuration error.
