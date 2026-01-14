<?php
/**
 * FILE: /core/response.php
 * ROLE: единые ответы (JSON/redirect)
 */

declare(strict_types=1);

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
