<?php
/**
 * FILE: /modules/modules/settings.php
 * ROLE: Паспорт модуля (справочно, НЕ источник меню/ACL)
 * CONNECTIONS:
 *  - меню и роли берутся из БД (таблица modules)
 */

declare(strict_types=1);

return [
    'name'         => 'Модули',
    'enabled'      => 1,
    'menu'         => 1,
    'sort'         => 20,
    'icon'         => 'bi-grid-3x3-gap',
    'roles'        => ['admin'], // справочно
    'has_settings' => 0,
];
