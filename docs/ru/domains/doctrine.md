# Интеграция с Doctrine

Бандл предоставляет готовые реализации persistence-интерфейсов (регистрируются как
сервисы, используются по алиасам в конфиге):

- `UniMethod\SymfonyLib\Integration\DoctrineLocator` — поиск по id;
- `UniMethod\SymfonyLib\Integration\DoctrineSaver` — сохранение;
- `UniMethod\SymfonyLib\Integration\DoctrineRemover` — удаление.

Если класс модели не обслуживается ни одним entity manager (не замаплен в
Doctrine), они бросают `EntityManagerNotFoundException` — ошибку конфигурации.

Ссылки (`reference`) и фильтры list по связи грузят целевую модель её
локатором: `impl.models.<модель>.entityLocator`, локатор read-действия или
`impl.defaultLocator` (см. документацию frame, `docs/ru/domains/models.md`,
«Локатор модели»). Если все модели — сущности Doctrine, достаточно одной строки:

```yaml
impl:
  defaultLocator: doctrine
  locators:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineLocator'
```

Для list-действий репозиторий пишется вами и реализует
`UniMethod\Frame\Persistence\EntityCollectionInterface` (см. документацию frame,
`docs/ru/domains/list.md` и `docs/ru/usage/list-action.md`). `list()` последним аргументом получает `array $included` —
плоский список путей связей, которые попадут в ответ; по нему делается eager
loading.

Бандл находит репозитории по тегу `symfony_lib.repository.collection`. Сервис с
`autoconfigure: true` получает его автоматически; без autoconfigure тег
указывается явно:

```yaml
# config/services.yaml
services:
  App\Repository\PostRepository:
    autoconfigure: false
    tags: ['symfony_lib.repository.collection']
```
