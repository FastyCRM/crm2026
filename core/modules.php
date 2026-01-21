<?php
/**
 * FILE: /core/modules.php
 * ROLE: Работа с модулями (меню и ACL) ТОЛЬКО через БД
 * CONNECTIONS:
 *  - использует db() из /core/db.php
 *
 * КАНОН:
 *  - settings.php модулей НЕ источник прав и НЕ источник меню
 *  - источник истины: таблица modules в БД
 *
 * ВАЖНО:
 *  - таблица modules у Бориса сейчас: code,name,icon,sort,enabled,menu,roles,has_settings (без icon_group)
 */

declare(strict_types=1);

/**
 * Нормализует icon под bootstrap-icons: гарантирует префикс bi-.
 */
function modules_norm_icon(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') return 'bi-dot';
    if (strpos($icon, 'bi-') !== 0) {
        $icon = 'bi-' . ltrim($icon, 'bi-');
    }
    return $icon;
}

/**
 * Парсит JSON ролей из БД.
 * Возвращает [] если NULL/пусто/битый json.
 *
 * @param mixed $rolesJson
 * @return array<int,string>
 */
function modules_parse_roles($rolesJson): array
{
    if ($rolesJson === null) return [];
    $s = trim((string)$rolesJson);
    if ($s === '') return [];

    $arr = json_decode($s, true);
    if (!is_array($arr)) return [];

    $out = [];
    foreach ($arr as $r) {
        $r = trim((string)$r);
        if ($r !== '') $out[] = $r;
    }
    return $out;
}

/**
 * Возвращает пункты меню админки по роли пользователя.
 *
 * Берёт ТОЛЬКО из таблицы modules:
 * enabled=1 AND menu=1
 * + фильтр по roles (если roles пустые -> доступ всем ролям)
 *
 * @param string $userRole admin|manager|user
 * @return array<int, array<string,mixed>>
 */
function modules_get_menu(string $userRole): array
{
    $pdo = db();

    // icon_group в таблице нет -> отдаём дефолт 'neutral'
    $sql = "
        SELECT code, name, icon, sort, roles
        FROM modules
        WHERE enabled = 1 AND menu = 1
        ORDER BY sort ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $items = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = (string)($row['code'] ?? '');
        if ($code === '') continue;

        $roles = modules_parse_roles($row['roles'] ?? null);
        if (count($roles) > 0 && !in_array($userRole, $roles, true)) {
            continue;
        }

        $items[] = [
            'code'       => $code,
            'title'      => (string)($row['name'] ?? $code),
            'href'       => '/adm/index.php?m=' . urlencode($code),
            'sort'       => (int)($row['sort'] ?? 100),
            'icon'       => modules_norm_icon((string)($row['icon'] ?? 'bi-dot')),
            'icon_group' => 'neutral', // дефолт, чтобы твой шаблон не ломался
        ];
    }

    return $items;
}

/**
 * Роли доступа к модулю берём ТОЛЬКО из таблицы modules.
 *
 * Если roles пустые/NULL -> доступ всем (для общих модулей).
 * Если строки в таблице нет -> [] (закроется acl_guard).
 *
 * @param string $moduleCode
 * @return array<int,string>
 */
function module_allowed_roles(string $moduleCode): array
{
    $moduleCode = trim($moduleCode);
    if ($moduleCode === '') return [];

    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT roles
        FROM modules
        WHERE code = :code AND enabled = 1
        LIMIT 1
    ");
    $stmt->execute([':code' => $moduleCode]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];

    return modules_parse_roles($row['roles'] ?? null);
}

/**
 * Проверка: включён ли модуль в БД.
 */
function module_is_enabled(string $moduleCode): bool
{
    $moduleCode = trim($moduleCode);
    if ($moduleCode === '') return false;

    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT enabled
        FROM modules
        WHERE code = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => $moduleCode]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    return ((int)($row['enabled'] ?? 0) === 1);
}
