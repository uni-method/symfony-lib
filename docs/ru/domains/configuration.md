# Конфигурация

## Параметры бандла

Бандл поддерживает **несколько независимых API** (мульти-API). Каждый API имеет
своё имя, свой файл конфига и свой префикс роутов. Создайте
`config/packages/symfony_lib.yaml`:

```yaml
symfony_lib:
    apis:
        main:
            config_path: '%kernel.project_dir%/config/api.yaml'
            prefix: '/api'
```

`config_path` и `prefix` обязательны. Имя API (`main`) произвольно и используется
во внутренних именах сервисов и роутов. Можно объявить несколько API — у каждого
свой конфиг и префикс.

Сервисы каждого API называются `symfony_lib.<вид>.<имя API>` (`symfony_lib.config.main`,
`symfony_lib.api_service.main`) и приватны; общих сервисов, привязанных к одному
из API, нет. Если приложению нужен конфиг конкретного API — внедрите его явно:

```php
public function __construct(
    #[Autowire(service: 'symfony_lib.config.main')]
    private Config $config,
) {
}
```

Конфиг API читается и валидируется при сборке контейнера и хранится в нём
сериализованным: в рантайме файл не разбирается. В debug-режиме контейнер
пересобирается при изменении файла; в prod после правки конфига нужен
`cache:clear`.

## Роуты

Создайте `config/routes/symfony_lib.yaml`:

```yaml
symfony_lib_apis:
    resource: .
    type: symfony_lib_multi_api
```

Тип `symfony_lib_multi_api` подключает делегирующий загрузчик, который сам строит
роуты для всех объявленных API.

## Файл API-конфига

Конфиг описывает API двумя секциями: `schema` (контракт — что есть) и `impl`
(реализация — PHP-классы и как читать/писать поля). Формат для JSON и YAML
идентичен; загрузчик выбирается по расширению (`.yaml`/`.yml` → Yaml, иначе JSON).

Минимальный `config/api.yaml`:

```yaml
schema:
  models:
    user:
      attributes:
        id:
          type: int
        email:
          type: string
  actions:
    user:
      read: {}
      create: {}
      delete: {}
impl:
  models:
    user:
      class: 'App\Entity\User'
      attributes:
        id:
          getter: getId
        email:
          getter: getEmail
          setter: setEmail
  actions:
    user:
      read:
        entityLocator: doctrine
      create:
        entitySaver: doctrine
      delete:
        entityLocator: doctrine
        entityRemover: doctrine
  locators:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineLocator'
  savers:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineSaver'
  removers:
    doctrine: 'UniMethod\SymfonyLib\Integration\DoctrineRemover'
```

Ключевые правила (полностью — в документации frame, `docs/ru/domains/config.md`):

- каждая модель/атрибут/связь из `schema` обязана иметь пару в `impl`, и наоборот
  (строгая двусторонняя парность);
- у каждой модели нужен атрибут `id`, который читается: getter-строка либо
  без getter-а (автоопределение публичного свойства `id`); `getter: false`
  запрещён;
- `entityLocator`/`entitySaver`/`entityRemover` — это алиасы из словарей
  `locators`/`savers`/`removers`.

## Эндпоинты

После настройки API доступен по префиксу. Для модели `user` с действиями
read/create/update/delete/list:

- `GET /api/user` — список
- `GET /api/user/{id}` — чтение
- `POST /api/user` — создание
- `PATCH /api/user/{id}` — обновление
- `DELETE /api/user/{id}` — удаление

Проверить зарегистрированные роуты:

```bash
php bin/console debug:router | grep symfony_lib
```
