# UniMethod Symfony Bundle — documentation

- [Philosophy](philosophy.md) — what the bundle does, principles.
- [Development](development.md) — for contributors: checks, test layout.

## Domains

- [Configuration](domains/configuration.md) — bundle parameters, routes, the API config file, endpoints.
- [Doctrine](domains/doctrine.md) — ready-made locator/saver/remover, repositories of list actions.
- [Security](domains/security.md) — user provider, roles, 401/403.
- [Constraints](domains/constraints.md) — shared constraints and your own.
- [Rate limit](domains/rate-limit.md) — rules, the default limiter, a custom implementation.
- [Errors](domains/errors.md) — bundle exceptions, observing domain exceptions.
- [Config validation](domains/config-validation.md) — the command and CI.
- [Customization](domains/customization.md) — your own locator/saver/remover, mutators.

## Usage

- [Quick start](usage/quickstart.md) — from installation to the first requests.

## Framework documentation

The `docs/en/` directory of the frame package (the paths below are inside it), the table of contents is in
`docs/en/README.md`: config (`docs/en/domains/config.md`), models and entity classes
(`docs/en/domains/models.md`), actions (`docs/en/domains/actions.md`), list
(`docs/en/domains/list.md`, `docs/en/usage/list-action.md`), persistence
(`docs/en/domains/persistence.md`), mutators (`docs/en/domains/mutators.md`),
constraints (`docs/en/domains/constraints.md`, `docs/en/usage/validation-patterns.md`),
errors (`docs/en/domains/errors.md`).
