# Philosophy

A Symfony bundle for integrating the `UniMethod\Frame` framework with Symfony applications.
It turns a declarative config (`schema` + `impl`) into a ready REST API: routing,
mapping the request body into entities and back, constraint checks, access
control and error handling.

## Principles

- **The bundle is a thin layer over frame.** The config, mapping, checks and the error
  model belong to frame; the bundle connects them with Symfony: routing, DI,
  Security, Validator, Doctrine, rate limit, the console check.
- **Each API is isolated.** Services are registered as a set for each API
  from `symfony_lib.apis` (`symfony_lib.<kind>.<API name>`), are private and
  are obtained via a ServiceLocator; there are no shared "first API" services. See
  [domains/configuration.md](domains/configuration.md).
- **Configuration errors — at container build time.** The API config is loaded and
  validated at build time, the services of the `impl` dictionaries are checked there too;
  the rest is caught by the check command before the first request. See
  [domains/config-validation.md](domains/config-validation.md),
  [domains/errors.md](domains/errors.md).
- **Fail-closed access.** Without a user provider a non-trivial `access`
  is forbidden. See [domains/security.md](domains/security.md).
- **Checks — with constraints.** Object access rules and invariants live in
  Symfony Validator constraints with action groups. See
  [domains/constraints.md](domains/constraints.md).
- **Integrations — depending on the installed package.** Doctrine, Security, Validator, the rate
  limiter and the console are connected if the package is installed. See
  [usage/quickstart.md](usage/quickstart.md).
