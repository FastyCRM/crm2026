<?php
/**
 * FILE: /modules/users/assets/php/handler.php
 * ROLE: обработчики Users (create/update/delete/set_role)
 * RULES:
 *  - абсолютные пути
 *  - auth + csrf + acl в каждом действии
 *  - manager: create/update only (NO delete, NO role)
 *  - admin: all
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    http_response_code(500);
    exit('ROOT_PATH not defined');
}

require_once ROOT_PATH . '/core/response.php';
require_once ROOT_PATH . '/core/security.php'; // clean(), csrf_token(), csrf_check()
require_once ROOT_PATH . '/core/auth.php';
require_once ROOT_PATH . '/core/acl.php';

// users service (если не подключён автолоадером)
$usersService = ROOT_PATH . '/modules/users/lib/service.php';
if (is_file($usersService)) {
    require_once $usersService;
}

// audit (если есть — используем)
$auditFile = ROOT_PATH . '/core/audit.php';
if (is_file($auditFile)) {
    require_once $auditFile;
}

/**
 * Safe audit call: никогда не должен ломать handler
 */
function users_audit(string $action, ?string $entity = null, $entityId = null, array $payload = [], string $level = 'info'): void
{
    try {
        if (function_exists('audit_log')) {
            // твоя сигнатура:
            // audit_log(string $module, string $action, ?string $entity, $entityId, array $payload, string $level)
            audit_log('users', $action, $entity, $entityId, $payload, $level);
        }
    } catch (Throwable $e) {
        // не ломаем основной поток
    }
}

$uid = auth_user_id();
if (!$uid) {
    json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$actorRole = auth_user_role(); // admin|manager|user|guest
$action = isset($_GET['a']) ? clean((string)$_GET['a']) : '';

/**
 * Все actions в users — только для admin/manager
 */
acl_guard(['admin', 'manager']);

/**
 * CSRF обязателен для POST
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : '';
    csrf_check($token);
}

switch ($action) {

    case 'create': {
        // manager allowed
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($name === '' || $email === '') {
            json_out(['ok' => false, 'error' => 'name/email required'], 400);
        }

        // роль задаёт только admin, manager всегда создаёт user
        $newRole = 'user';
        if ($actorRole === 'admin') {
            $postedRole = isset($_POST['role']) ? clean((string)$_POST['role']) : 'user';
            if (in_array($postedRole, ['admin', 'manager', 'user'], true)) {
                $newRole = $postedRole;
            }
        }

        $id = users_create([
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'role'     => $newRole,
            'password' => $pass,
        ]);

        users_audit('create', 'user', (int)$id, [
            'actor_role' => $actorRole,
            'actor_id'   => (int)$uid,
            'email'      => $email,
            'phone'      => $phone,
            'role'       => $newRole,
        ], 'info');

        json_out(['ok' => true, 'id' => (int)$id]);
    }

    case 'update': {
        // manager allowed (но без роли)
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
        if ($name === '' || $email === '') json_out(['ok' => false, 'error' => 'name/email required'], 400);

        users_update($id, [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        $passChanged = 0;
        if ($pass !== '') {
            users_set_password($id, $pass);
            $passChanged = 1;
        }

        users_audit('update', 'user', (int)$id, [
            'actor_role'   => $actorRole,
            'actor_id'     => (int)$uid,
            'email'        => $email,
            'phone'        => $phone,
            'pass_changed' => $passChanged,
        ], 'info');

        json_out(['ok' => true]);
    }

    case 'delete': {
        // ONLY admin (manager не может удалять)
        acl_guard(['admin']);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);

        // запрет удалить самого себя
        if ($id === (int)$uid) {
            json_out(['ok' => false, 'error' => 'Нельзя удалить самого себя'], 400);
        }

        users_delete($id);

        users_audit('delete', 'user', (int)$id, [
            'actor_role' => $actorRole,
            'actor_id'   => (int)$uid,
        ], 'warn');

        json_out(['ok' => true]);
    }

    case 'set_role': {
        // ONLY admin
        acl_guard(['admin']);

        $id = (int)($_POST['id'] ?? 0);
        $role = clean((string)($_POST['role'] ?? 'user'));

        if ($id <= 0) json_out(['ok' => false, 'error' => 'id required'], 400);
        if (!in_array($role, ['admin', 'manager', 'user'], true)) {
            json_out(['ok' => false, 'error' => 'invalid role'], 400);
        }

        // запрет поменять роль самому себе
        if ($id === (int)$uid) {
            json_out(['ok' => false, 'error' => 'Нельзя менять роль самому себе'], 400);
        }

        users_set_role($id, $role);

        users_audit('set_role', 'user', (int)$id, [
            'actor_role' => $actorRole,
            'actor_id'   => (int)$uid,
            'role'       => $role,
        ], 'warn');

        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Unknown action'], 404);
}
