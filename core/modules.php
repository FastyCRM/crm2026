<?php
/**
 * FILE: /core/modules.php
 * ROLE: загрузка settings.php модулей (и roles доступа)
 */

declare(strict_types=1);

/**
 * Возвращает массив settings модуля или [].
 */
function module_settings(string $moduleCode): array
{
    $path = ROOT_PATH . '/modules/' . $moduleCode . '/settings.php';
    if (!is_file($path)) return [];

    $cfg = require $path;
    return is_array($cfg) ? $cfg : [];
}

/**
 * Роли доступа к модулю берём ТОЛЬКО из settings.php
 */
function module_allowed_roles(string $moduleCode): array
{
    $s = module_settings($moduleCode);
    $roles = $s['roles'] ?? [];
    return is_array($roles) ? $roles : [];
}
