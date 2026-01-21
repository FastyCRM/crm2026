<?php
/**
 * FILE: /modules/modules/assets/php/handler.php
 * ROLE: Handler модуля "modules" — только включить/выключить enabled
 * CONNECTIONS:
 *  - вызывается через /adm/index.php?m=modules&a=toggle
 *  - использует:
 *      clean() + csrf_check() из /core/security.php
 *      db() из /core/db.php
 *      redirect() из /core/response.php (или где у тебя)
 *      auth_user_id(), module_allowed_roles(), acl_guard()
 */

declare(strict_types=1);

// защита от прямого доступа к handler.php
if (!defined('ROOT_PATH')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

$uid = auth_user_id();
if (!$uid) {
    http_response_code(401);
    exit('Unauthorized');
}

// ACL: только admin (роли берём из БД)
$allowed = module_allowed_roles('modules');
acl_guard($allowed);

$a = isset($_GET['a']) ? clean((string)$_GET['a']) : '';
if ($a !== 'toggle') {
    redirect('/adm/index.php?m=modules');
}

// CSRF: у тебя функция сама делает 403+exit
csrf_check($_POST['_csrf'] ?? null);

$code = isset($_POST['code']) ? clean((string)$_POST['code']) : '';
$enabled = (int)($_POST['enabled'] ?? 0) === 1 ? 1 : 0;

if ($code === '' || !preg_match('~^[a-z0-9_]+$~i', $code)) {
    http_response_code(400);
    exit('Bad module code');
}

// защита от самоубийства
if ($code === 'auth' || $code === 'modules') {
    http_response_code(400);
    exit('Protected module');
}

$pdo = db();

$stmt = $pdo->prepare("
    UPDATE modules
    SET enabled = :enabled
    WHERE code = :code
    LIMIT 1
");
$stmt->execute([
    ':enabled' => $enabled,
    ':code' => $code,
]);

redirect('/adm/index.php?m=modules');
