
/modules/modules/
  modules.php
  settings.php

  /assets/
    /css/
      modules.css
    /js/
      modules.js
    /php/
      handler.php

  README.md


# modules

Модуль управления модулями.

## Источник истины
Только таблица `modules` в БД:
- enabled
- menu
- sort
- icon
- roles (JSON)

## Доступ
Только `admin` (ACL берётся из БД через `module_allowed_roles('modules')`).

## Действия
- create: добавить запись
- update: обновить поля
- delete: удалить запись (кроме auth/modules)

