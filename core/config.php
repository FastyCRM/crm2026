<?php
/**
 * FILE: /core/config.php
 * ROLE: Конфиг БД и безопасности
 */

declare(strict_types=1);

return [
  'db' => [
    'host' => 'localhost',
    'name' => 'crm2026',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],
  'security' => [
    'app_secret' => 'CHANGE_ME__LONG_RANDOM_SECRET__64+_CHARS',
    'session_cookie_name' => 'mini_crm_sid',
    'remember_lifetime_sec' => 60 * 60 * 24 * 14,   // 14 дней
    'login_max_attempts' => 7,
    'login_lock_minutes' => 15,
    'reset_token_lifetime_min' => 30,               // токен восстановления 30 минут
  ],
];
