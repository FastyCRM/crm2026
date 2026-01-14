<?php
declare(strict_types=1);

/**
 * FILE: /modules/auth/settings.php
 * ROLE: Метаданные модуля авторизации
 */

return [
    'enabled'      => 1,
    'menu'         => 0, // auth не показываем в меню
    'name'         => 'Авторизация',

    // Bootstrap Icons
    'icon'         => 'bi-box-arrow-in-right',
    'icon_group'   => 'neutral',

    'sort'         => 0,

    'roles' => ['admin', 'manager', 'user'],

    'has_settings' => 0,
];
