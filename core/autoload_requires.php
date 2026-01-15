<?php
/**
 * FILE: /core/autoload_requires.php
 * ROLE: безопасная автоподгрузка зависимостей по settings.php активного модуля
 * CONNECTIONS:
 *  - вызывается из /core/bootstrap.php (один раз)
 * RULES:
 *  - подключаем только то, что:
 *      a) находится в белом списке core alias (core:*), ИЛИ
 *      b) экспортируется модулем через settings.php['exports'] (module:*)
 *  - никаких произвольных путей, никаких ../../
 *  - только абсолютные пути require_once
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    throw new RuntimeException('ROOT_PATH not defined');
}

/**
 * Белый список core-сервисов (платформенные штуки).
 * Сюда добавляем редко: db, json, audit и т.д.
 */
function core_require_map(): array
{
    return [
        'core:db'       => ROOT_PATH . '/core/db.php',
        'core:security' => ROOT_PATH . '/core/security.php',
        'core:response' => ROOT_PATH . '/core/response.php',

        // CSRF живет в security.php
        'core:csrf'     => ROOT_PATH . '/core/security.php',

        'core:auth'     => ROOT_PATH . '/core/auth.php',

        // если acl_guard тоже в security.php — тоже туда
        'core:acl'      => ROOT_PATH . '/core/security.php',
    ];
}


/**
 * Безопасное имя модуля из запроса (?m=).
 */
function core_detect_active_module(): string
{
    $m = $_GET['m'] ?? '';
    $m = is_string($m) ? $m : '';
    $m = trim($m);

    // whitelist символов
    $m = preg_replace('/[^a-zA-Z0-9_\-]/', '', $m);
    return $m ?: '';
}

/**
 * Загрузить settings.php указанного модуля.
 */
function core_load_module_settings(string $module): array
{
    if ($module === '') return [];
    $settingsFile = ROOT_PATH . '/modules/' . $module . '/settings.php';
    if (!is_file($settingsFile)) return [];

    $cfg = require $settingsFile;
    return is_array($cfg) ? $cfg : [];
}

/**
 * Просканить все модули и собрать карту exports:
 * alias => абсолютный путь к экспортируемому файлу
 *
 * exports в settings.php:
 * 'exports' => [
 *    'module:users' => 'lib/service.php',
 * ]
 */
function core_collect_module_exports(): array
{
    $dir = ROOT_PATH . '/modules';
    if (!is_dir($dir)) return [];

    $result = [];
    $list = scandir($dir);
    if ($list === false) return [];

    foreach ($list as $module) {
        if ($module === '.' || $module === '..') continue;

        $modulePath = $dir . '/' . $module;
        if (!is_dir($modulePath)) continue;

        $cfg = core_load_module_settings($module);
        if (!$cfg) continue;

        // можно учитывать enabled, чтобы не подгружать сервисы выключенных модулей
        if ((int)($cfg['enabled'] ?? 0) !== 1) continue;

        $exports = $cfg['exports'] ?? [];
        if (!is_array($exports) || !$exports) continue;

        foreach ($exports as $alias => $relPath) {
            if (!is_string($alias) || !is_string($relPath)) continue;

            $alias = trim($alias);
            $relPath = trim($relPath);
            if ($alias === '' || $relPath === '') continue;

            // ЖЕСТКО: только module:*
            if (strpos($alias, 'module:') !== 0) continue;

            // запрет на выход из папки модуля
            if (strpos($relPath, '..') !== false) continue;

            // запрет абсолютного пути
            if (strpos($relPath, '/') === 0) continue;

            $abs = ROOT_PATH . '/modules/' . $module . '/' . $relPath;
            if (!is_file($abs)) continue;

            // первая регистрация выигрывает (чтобы не было коллизий)
            if (!isset($result[$alias])) {
                $result[$alias] = $abs;
            }
        }
    }

    return $result;
}

/**
 * Подключить алиас зависимости (core:* или module:*)
 */
function core_require_alias(string $alias, array $coreMap, array $moduleExports): void
{
    $alias = trim($alias);
    if ($alias === '') return;

    if (isset($coreMap[$alias])) {
        $file = $coreMap[$alias];
        if (!is_file($file)) {
            throw new RuntimeException("Core require file not found: {$file} ({$alias})");
        }
        require_once $file;
        return;
    }

    if (isset($moduleExports[$alias])) {
        $file = $moduleExports[$alias];
        if (!is_file($file)) {
            throw new RuntimeException("Module export file not found: {$file} ({$alias})");
        }
        require_once $file;
        return;
    }

    throw new RuntimeException("Unknown require alias: {$alias}");
}

/**
 * Главная функция:
 *  - определяет активный модуль
 *  - читает requires из его settings.php
 *  - подключает зависимости (core:* + module:*)
 */
function core_autoload_requires_for_active_module(): void
{
    $module = core_detect_active_module();
    if ($module === '') return;

    $cfg = core_load_module_settings($module);
    if (!$cfg) return;

    $requires = $cfg['requires'] ?? [];
    if (!is_array($requires) || !$requires) return;

    $coreMap = core_require_map();
    $moduleExports = core_collect_module_exports();

    foreach ($requires as $alias) {
        if (!is_string($alias)) continue;
        core_require_alias($alias, $coreMap, $moduleExports);
    }
}
