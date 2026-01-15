<?php
/**
 * FILE: /core/response.php
 * ROLE: единые ответы (JSON/redirect)
 */

declare(strict_types=1);

if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_out')) {
    function json_out(array $data, int $code = 200): void
    {
        json_response($data, $code);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
