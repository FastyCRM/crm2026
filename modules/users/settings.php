<?php
/**
 * FILE: /modules/users/settings.php
 * ROLE: метаданные модуля Users + экспорт сервиса
 * RULES:
 *  - роли определяют доступ к модулю в меню
 *  - exports публикует сервис наружу
 *  - requires подключает нужные зависимости (core:* и module:*)
 */
declare(strict_types=1);

return [
    'enabled'      => 1,
    'menu'         => 1,
    'name'         => 'Пользователи',
    'icon'         => 'bi-people',
    'icon_group'   => 'users',
    'sort'         => 30,
    'roles'        => ['admin', 'manager'], // user не видит модуль
    'has_settings' => 0,

    /**
     * Экспорт сервиса users (функции users_*).
     * Другие модули могут писать requires => ['module:users'].
     */
    'exports' => [
        'module:users' => 'lib/service.php',
    ],

    /**
     * Сам модуль users тоже требует свой сервис + базовые core штуки.
     * Можно не писать module:users (т.к. view/handler могут подключить напрямую),
     * но мы делаем единообразно: всё через requires.
     */
    'requires' => [
        'core:db',
        'core:security',
        'core:response',
        'core:auth',
        'module:users',
    ],
];
