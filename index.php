<?php
declare(strict_types=1);

define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/core/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rtrim($uri, '/') ?: '/';

/**
 * PHP 7-safe проверка начала строки
 */
if ($uri === '/adm' || strpos($uri, '/adm/') === 0) {
    require_once ROOT_PATH . '/adm/index.php';
    exit;
}

require_once ROOT_PATH . '/site/index.php';
