# Ограничения (валидация и доступ к объекту)

Проверка ограничений делегируется Symfony Validator через
`SymfonyConstraintChecker` (`ConstraintCheckerInterface`). Фреймворк вызывает его
в трёх фазах и передаёт контекст (действие, фаза, текущий пользователь, id,
снимок объекта до изменений); мост проецирует контекст в группы валидации:

| Фаза | Действия | Группы | Объект |
|---|---|---|---|
| `AfterLocate` | read, update, delete | `[<action>]` | сущность из БД, до применения входа |
| `AfterInput` | create, update | `['Default', <action>]` | сущность/DTO с применённым входом |
| `ListQuery` | list | `['Default']` | `{Model}ListRequest` |

Атрибут `Assert\*` без `groups` выполняется только на входе (`AfterInput`,
`ListQuery`). Правило «кому можно этот объект» помечается группами действий и
выполняется на объекте из БД до маппинга — кастомный локатор для этого не нужен.
Нарушения возвращаются как 422 с `source.pointer`; прерывающие исходы (доступ)
— `UniMethod\SymfonyLib\Exception\AccessDeniedException` → 403.

Без `symfony/validator` подключается заглушка `NullConstraintChecker`: нарушений
нет, ограничения ложатся на сейверы.

## Общие констрейнты

Поставляются бандлом (`UniMethod\SymfonyLib\Constraint`), не зависят от
security-bundle и Doctrine — пользователь и снимок «до» берутся из контекста.
Иерархия ролей учитывается, если есть `UserProviderInterface` (security-bundle).

Пути в констрейнтах (`path`, `identityPath`) проверяются строго: если сегмента
нет на классе или значение по пути не того вида (например, `path` ведёт не к
`UserInterface`), констрейнт бросает `ConstraintPathMisconfiguredException` —
ошибку конфигурации, 500. Опечатка в пути не отключает правило молча. Значение
`null` по существующему пути — штатный случай, он обрабатывается, как описано
у каждого констрейнта.

`OwnedByCurrentUser` — объект доступен владельцу по пути до `User` и ролям из
`exceptRoles`; иначе 403. Вешается на класс сущности с группами действий, где
объект уже существует:

```php
#[OwnedByCurrentUser(
    path: 'author',
    exceptRoles: [User::ROLE_ADMIN],
    message: 'Пост принадлежит другому пользователю',
    groups: ['read', 'update', 'delete'],
)]
class Post
```

`ScopedToCurrentUser` — владение на списке: фильтр по пользователю обязан быть
заполнен (иначе 422) и равен идентификатору текущего пользователя (иначе 403);
роли из `exceptRoles` не ограничены. Вешается на `{Model}ListRequest`;
`required: true` в правиле фильтра при этом не ставится — иначе он станет
обязательным и для исключённых ролей:

```php
#[ScopedToCurrentUser(path: 'filter.equal.userId', exceptRoles: [User::ROLE_ADMIN])]
final class OrderListRequest
```

`HasRole` — заполненное значение свойства требует одной из ролей, пустое
пропускается; нет роли — 403. Для полей, которые в одном действии доступны
разным ролям:

```php
#[HasRole([User::ROLE_MANAGER, User::ROLE_ADMIN], message: 'Скидку задаёт менеджер')]
private ?int $discount = null;
```

Пустые значения — только `null`, `''` и `[]`. `false`, `0`, `'0'` и пустой
объект считаются заполненными: поле `bool $featured = false` потребует роль у
всех, даже у тех, кто его не передавал. Для флагов и счётчиков используйте
nullable-тип с дефолтом `null`:

```php
#[HasRole(User::ROLE_ADMIN)]
private ?bool $featured = null;
```

`UnchangedOnUpdate` — значение нельзя менять у существующей сущности:
сравнивается со снимком до применения входа; нарушение 422. Свойство при этом
остаётся в `writable` у update, иначе клиент получит общее «not writable» вместо
доменного текста:

```php
#[UnchangedOnUpdate(message: 'Зону нельзя перенести в другой огород', groups: ['update'])]
private ?Garden $garden = null;
```

## Свой констрейнт

Валидатор получает контекст через `ConstraintContextHolder`
(`ConstraintContext`: `actionType`, `phase`, `user`, `id`, `original`), пути
читает `PropertyPathReader`, роли проверяет `RoleChecker`. Регистрируется как
обычный сервис — тег `validator.constraint_validator` даёт autoconfigure.
Прерывающий исход — бросить `AccessDeniedException`; накапливаемый —
`buildViolation(...)->atPath(...)`. Если `PropertyPathReader` вернул «путь
недостижим», это ошибка конфигурации констрейнта: бросайте
`ConstraintPathMisconfiguredException`, а не `return`. Валидатор, вызванный вне
`ConstraintCheckerInterface::check()`, получит `ConstraintContextNotSetException`
при обращении к `ConstraintContextHolder::get()`.

Что описывать атрибутами, а что секцией `constraints` конфига (генерируется
прибилдом в метаданные Symfony Validator и не повторяется в коде) — в
документации frame, `docs/ru/domains/constraints.md`.
