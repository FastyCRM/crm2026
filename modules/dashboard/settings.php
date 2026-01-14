<?php
declare(strict_types=1);

/**
 * FILE: /modules/dashboard/settings.php
 * ROLE: Главная страница админки
 */

return [
    'enabled'      => 1,
    'menu'         => 1,
    'name'         => 'Главная',

    // Bootstrap Icons
    'icon'         => 'bi-house',
    'icon_group'   => 'crm',

    'sort'         => 10,

    // доступ только залогиненным
    'roles'        => ['admin', 'manager', 'user'],

    'has_settings' => 0,
];
