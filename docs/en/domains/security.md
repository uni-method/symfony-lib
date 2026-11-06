# Integration with Symfony Security

Access control is connected via `SymfonyUserProvider`
(`UserProviderInterface`). `access` can be set in the action config:

```yaml
schema:
  actions:
    user:
      list:
        access:
          authRequired: true
          roles: ['ROLE_ADMIN']
```

Roles are checked taking the Symfony role hierarchy into account.

A denial of access to an action, to a `writable` path and to a filter operator is 401 if
`authRequired` and there is no current user, otherwise 403. The current user is
a Symfony Security user implementing `UniMethod\Frame\Integration\UserInterface`;
any other one is considered anonymous.
