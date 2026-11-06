# Constraints (validation and object access)

Constraint checking is delegated to Symfony Validator via
`SymfonyConstraintChecker` (`ConstraintCheckerInterface`). The framework calls it
in three phases and passes the context (action, phase, current user, id,
a snapshot of the object before changes); the bridge projects the context into validation groups:

| Phase | Actions | Groups | Object |
|---|---|---|---|
| `AfterLocate` | read, update, delete | `[<action>]` | entity from the DB, before input is applied |
| `AfterInput` | create, update | `['Default', <action>]` | entity/DTO with input applied |
| `ListQuery` | list | `['Default']` | `{Model}ListRequest` |

An `Assert\*` attribute without `groups` runs only on input (`AfterInput`,
`ListQuery`). The rule "who is allowed this object" is marked with action groups and
runs on the object from the DB before mapping — a custom locator is not needed for this.
Violations are returned as 422 with `source.pointer`; interrupting outcomes (access)
— `UniMethod\SymfonyLib\Exception\AccessDeniedException` → 403.

Without `symfony/validator` the `NullConstraintChecker` stub is connected: there are no
violations, constraints fall on the savers.

## Shared constraints

They are shipped with the bundle (`UniMethod\SymfonyLib\Constraint`) and do not depend on
security-bundle and Doctrine — the user and the "before" snapshot are taken from the context.
The role hierarchy is taken into account if there is a `UserProviderInterface` (security-bundle).

Paths in constraints (`path`, `identityPath`) are checked strictly: if the segment
does not exist on the class or the value at the path is of the wrong kind (for example, `path` does not lead to
a `UserInterface`), the constraint throws `ConstraintPathMisconfiguredException` —
a configuration error, 500. A typo in the path does not silently disable the rule. A `null`
value at an existing path is a regular case, it is handled as described
for each constraint.

`OwnedByCurrentUser` — the object is available to the owner by the path to `User` and to the roles from
`exceptRoles`; otherwise 403. It is put on the entity class with the groups of actions where
the object already exists:

```php
#[OwnedByCurrentUser(
    path: 'author',
    exceptRoles: [User::ROLE_ADMIN],
    message: 'The post belongs to another user',
    groups: ['read', 'update', 'delete'],
)]
class Post
```

`ScopedToCurrentUser` — ownership on a list: the filter by user must be
filled (otherwise 422) and equal to the identifier of the current user (otherwise 403);
roles from `exceptRoles` are not restricted. It is put on `{Model}ListRequest`;
`required: true` is not set in the filter rule in this case — otherwise it would become
required for the excluded roles too:

```php
#[ScopedToCurrentUser(path: 'filter.equal.userId', exceptRoles: [User::ROLE_ADMIN])]
final class OrderListRequest
```

`HasRole` — a filled property value requires one of the roles, an empty one
is skipped; no role — 403. For fields that are available to different roles
in one action:

```php
#[HasRole([User::ROLE_MANAGER, User::ROLE_ADMIN], message: 'The discount is set by a manager')]
private ?int $discount = null;
```

Empty values are only `null`, `''` and `[]`. `false`, `0`, `'0'` and an empty
object are considered filled: a `bool $featured = false` field will require the role from
everyone, even from those who did not pass it. For flags and counters use
a nullable type with a `null` default:

```php
#[HasRole(User::ROLE_ADMIN)]
private ?bool $featured = null;
```

`UnchangedOnUpdate` — the value cannot be changed on an existing entity:
it is compared with the snapshot before input is applied; a violation is 422. At the same time the property
remains in `writable` of update, otherwise the client gets the generic "not writable" instead of
the domain text:

```php
#[UnchangedOnUpdate(message: 'A zone cannot be moved to another garden', groups: ['update'])]
private ?Garden $garden = null;
```

## Your own constraint

The validator gets the context via `ConstraintContextHolder`
(`ConstraintContext`: `actionType`, `phase`, `user`, `id`, `original`), paths
are read by `PropertyPathReader`, roles are checked by `RoleChecker`. It is registered as
a regular service — the `validator.constraint_validator` tag is given by autoconfigure.
An interrupting outcome — throw `AccessDeniedException`; an accumulated one —
`buildViolation(...)->atPath(...)`. If `PropertyPathReader` returned "the path is
unreachable", this is a constraint configuration error: throw
`ConstraintPathMisconfiguredException`, not `return`. A validator called outside
`ConstraintCheckerInterface::check()` gets `ConstraintContextNotSetException`
when accessing `ConstraintContextHolder::get()`.

What to describe with attributes and what with the `constraints` section of the config (generated
by the prebuild into Symfony Validator metadata and not repeated in code) — in
the frame documentation, `docs/en/domains/constraints.md`.
