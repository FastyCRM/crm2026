<?php
/**
 * FILE: /core/bootstrap.php
 * ROLE: старт проекта: подключения core, сессия, remember restore
 */

declare(strict_types=1);

require_once ROOT_PATH . '/core/db.php';
require_once ROOT_PATH . '/core/response.php';
require_once ROOT_PATH . '/core/security.php';
require_once ROOT_PATH . '/core/modules.php';
require_once ROOT_PATH . '/core/auth.php';
require_once ROOT_PATH . '/core/acl.php';

$cfg = require ROOT_PATH . '/core/config.php';

session_name($cfg['security']['session_cookie_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/**
 * Автовход по remember-cookie (если есть).
 */
auth_restore_from_cookie();
