<?php
/**
 * FILE: /modules/oauth_tokens/settings.php
 * ROLE: Метаданные модуля
 * CONNECTIONS: none
 */

declare(strict_types=1);

return [
    'enabled' => 1,
    'menu' => 1,
    'name' => 'OAuth токены',
    'icon' => 'bi bi-key',
    'sort' => 50,
    'roles' => 'admin,user',   // менеджера нет
    'has_settings' => 0,
];
