<?php
/**
 * FILE: /core/bootstrap.php
 * ROLE: старт проекта: конфиг, сессия, error handlers, подключения core, autoload requires, remember restore
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    throw new RuntimeException('ROOT_PATH not defined');
}

/**
 * 1) Конфиг (нужен до session_name)
 */
$cfg = require ROOT_PATH . '/core/config.php';

/**
 * 2) Сессия (должна быть ДО auth/csrf/acl)
 */
session_name($cfg['security']['session_cookie_name'] ?? 'crm2026');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/**
 * 3) Логи и глобальные перехватчики
 * ВАЖНО: папка /logs должна существовать
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/logs/error.log');

set_error_handler(function($severity, $message, $file, $line) {
    $msg = '['.date('Y-m-d H:i:s')."] PHP_ERROR {$severity}: {$message} in {$file}:{$line}\n";
    error_log($msg);
    return false;
});

set_exception_handler(function(Throwable $e) {
    $msg = '['.date('Y-m-d H:i:s')."] EXCEPTION: ".$e->getMessage()." in ".$e->getFile().":".$e->getLine()."\n"
         . $e->getTraceAsString()."\n\n";
    error_log($msg);

    http_response_code(500);

    // ajax/json
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exit('Server error');
});

/**
 * 4) Базовые core подключения
 */
require_once ROOT_PATH . '/core/db.php';
require_once ROOT_PATH . '/core/response.php';
require_once ROOT_PATH . '/core/security.php'; // тут у тебя clean + csrf_* (на самом деле csrf внутри security.php)
require_once ROOT_PATH . '/core/modules.php';
require_once ROOT_PATH . '/core/auth.php';
require_once ROOT_PATH . '/core/acl.php';
require_once ROOT_PATH . '/core/audit.php';


/**
 * 5) Автоподгрузка зависимостей активного модуля (requires)
 */
require_once ROOT_PATH . '/core/autoload_requires.php';
core_autoload_requires_for_active_module();

/**
 * 6) Автовход по remember-cookie (если есть)
 * Важно делать ПОСЛЕ session_start и ПОСЛЕ загрузки auth.php
 */
auth_restore_from_cookie();
