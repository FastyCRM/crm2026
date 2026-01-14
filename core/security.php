<?php
/**
 * FILE: /core/security.php
 * ROLE: CSRF + чистка ввода
 */

declare(strict_types=1);

/**
 * Убирает HTML и пробелы. Используется для простых полей.
 * ВАЖНО: SQL безопасность обеспечивает PDO prepare().
 */
function clean(string $s): string
{
    return trim(strip_tags($s));
}

/**
 * CSRF токен в сессии.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * Проверка CSRF токена.
 */
function csrf_check(?string $token): void
{
    $ok = is_string($token)
        && isset($_SESSION['_csrf'])
        && is_string($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);

    if (!$ok) {
        http_response_code(403);
        exit('CSRF validation failed');
    }
}
