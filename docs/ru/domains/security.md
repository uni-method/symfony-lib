# Интеграция с Symfony Security

Контроль доступа подключается через `SymfonyUserProvider`
(`UserProviderInterface`). В конфиге действия можно задать `access`:

```yaml
schema:
  actions:
    user:
      list:
        access:
          authRequired: true
          roles: ['ROLE_ADMIN']
```

Роли проверяются с учётом иерархии ролей Symfony.

Отказ в доступе к действию, к `writable`-пути и к оператору фильтра — 401, если
`authRequired`, а текущего пользователя нет, иначе 403. Текущий пользователь —
пользователь Symfony Security, реализующий `UniMethod\Frame\Integration\UserInterface`;
любой другой считается анонимным.
