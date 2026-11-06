# Кастомизация

## Свои Locator / Saver / Remover

1. Реализуйте нужный интерфейс из `UniMethod\Frame\Persistence`
   (`EntityLocatorInterface`, `EntitySaverInterface`, `EntityRemoverInterface`).
2. Зарегистрируйте класс как сервис (публичным он быть не обязан). Если
   сервиса нет в контейнере или он не реализует интерфейс, сборка контейнера
   упадёт с `ImplServiceNotFoundException` / `ImplServiceTypeMismatchException`.
3. Добавьте алиас в соответствующий словарь конфига:

```yaml
impl:
  savers:
    my_custom: 'App\Api\Saver\MyCustomSaver'
```

Локатор отвечает только на «найти по id»; проверка «можно ли текущему
пользователю этот объект» — констрейнт с группами действий (см. [constraints.md](constraints.md)),
не кастомный локатор.

## Мутаторы

1. Реализуйте `AttributeMutatorInterface` или `RelationMutatorInterface`
   (`UniMethod\Frame\Mutator`).
2. Зарегистрируйте как сервис (проверяется так же, как для
   locator/saver/remover).
3. Объявите в конфиге: словарь `impl.mutators`/`impl.relationMutators` и привязку
   по фазам в `schema`.
