<?php
/**
 * FILE: /core/audit.php
 * ROLE: аудит действий и ошибок в БД (таблица audit_log)
 * CONNECTIONS:
 *  - подключается из /core/bootstrap.php (или там, где уже цепляешь core файлы)
 * RULES:
 *  - audit не должен ломать приложение: любые ошибки внутри audit только в error_log
 */

declare(strict_types=1);

/**
 * Записать событие в аудит.
 *
 * @param string $module   Код модуля (users, auth, leads...)
 * @param string $action   Действие (create/update/delete/login...)
 * @param string|null $entity     Сущность (user, lead, task...)
 * @param mixed $entityId         id сущности
 * @param array $payload          доп данные (что меняли)
 * @param string $level           info|warn|error
 */
function audit_log(string $module, string $action, ?string $entity = null, $entityId = null, array $payload = [], string $level = 'info'): void
{
    try {
        if (!function_exists('db')) {
            error_log('[AUDIT_FAIL] db() not found');
            return;
        }

        $uid  = function_exists('auth_user_id') ? auth_user_id() : null;
        $role = function_exists('auth_user_role') ? auth_user_role() : null;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $json = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        if ($json === false) $json = null;

        $st = db()->prepare("
            INSERT INTO audit_log
                (user_id, role, module, action, entity, entity_id, level, payload, ip, user_agent)
            VALUES
                (:uid, :role, :module, :action, :entity, :eid, :lvl, :payload, INET6_ATON(:ip), :ua)
        ");

        $st->execute([
            ':uid'    => $uid ?: null,
            ':role'   => $role ?: null,
            ':module' => $module ?: null,
            ':action' => $action,
            ':entity' => $entity,
            ':eid'    => $entityId !== null ? (string)$entityId : null,
            ':lvl'    => $level,
            ':payload'=> $json,
            ':ip'     => $ip,
            ':ua'     => $ua,
        ]);
    } catch (Throwable $e) {
        error_log('[AUDIT_FAIL] ' . $e->getMessage());
    }
}

/**
 * Утилита: залогировать ошибку как событие.
 */
function audit_error(string $module, string $where, string $message, array $payload = []): void
{
    $payload = array_merge($payload, [
        'where' => $where,
        'message' => $message,
    ]);
    audit_log($module, 'error', null, null, $payload, 'error');
}
