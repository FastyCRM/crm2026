<?php
/**
 * FILE: /core/acl.php
 * ROLE: user roles + guard
 */

declare(strict_types=1);

/**
 * Возвращает массив кодов ролей пользователя.
 */
function user_roles(int $userId): array
{
    $st = db()->prepare(
        'SELECT r.code
         FROM roles r
         JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = :uid'
    );
    $st->execute([':uid' => $userId]);
    $rows = $st->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        if (isset($r['code'])) $out[] = (string)$r['code'];
    }
    return $out;
}

/**
 * Guard: проверяет, что пользователь авторизован и имеет одну из разрешённых ролей.
 */
function acl_guard(array $allowedRoles): void
{
    $uid = auth_user_id();
    if (!$uid) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $roles = user_roles($uid);
    foreach ($allowedRoles as $ar) {
        if (in_array($ar, $roles, true)) return;
    }

    http_response_code(403);
    exit('Forbidden');
}
