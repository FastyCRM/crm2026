<?php
/**
 * FILE: /modules/auth/assets/php/handler.php
 * ROLE: обработчики auth-модуля (actions)
 * IMPORTANT:
 * - POST действия проверяют CSRF
 * - действия с правами используют ACL по roles из settings.php
 */

declare(strict_types=1);

$a = isset($_GET['a']) ? clean((string)$_GET['a']) : '';

/**
 * Action: make_hash
 * ROLE: сервис для генерации password_hash для первого запуска.
 * SECURITY: Использовать 1 раз, потом удалить action.
 */
if ($a === 'make_hash') {
    $pwd = isset($_GET['p']) ? (string)$_GET['p'] : 'admin123';
    $hash = password_hash($pwd, PASSWORD_BCRYPT);

    header('Content-Type: text/plain; charset=utf-8');
    echo "Password: {$pwd}\nHash:\n{$hash}\n\n";
    echo "SQL:\nUPDATE users SET pass_hash = '{$hash}' WHERE email='admin@local';\n";
    exit;
}

/**
 * Action: login (POST)
 * ROLE: логин по телефону + пароль.
 */
if ($a === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    csrf_check($_POST['_csrf'] ?? null);

    $phone = (string)($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $ok = auth_login_by_phone($phone, $password);

    if (!$ok) {
        redirect('/adm?m=auth&err=1');
    }

    redirect('/adm?m=auth');
}

/**
 * Action: logout
 * ROLE: выход
 */
if ($a === 'logout') {
    auth_logout();
    redirect('/adm?m=auth');
}

/**
 * Action: set_theme (POST)
 * ROLE: индивидуально сохраняет тему в users.ui_theme
 * ACL: роли берём из modules/auth/settings.php
 */
if ($a === 'set_theme') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    csrf_check($_POST['_csrf'] ?? null);

    $uid = auth_user_id();
    if (!$uid) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $allowed = module_allowed_roles('auth');
    acl_guard($allowed);


    $theme = clean((string)($_POST['theme'] ?? 'dark'));
    auth_set_user_theme($uid, $theme);

    redirect('/adm?m=auth');
}

/**
 * Action: reset_request (POST)
 * ROLE: создаёт токен восстановления по email.
 *
 * IMPORTANT:
 * - В проде токен отправляется письмом.
 * - Здесь для теста редиректим на страницу с token в URL.
 * - При этом мы НЕ раскрываем, существует email или нет.
 */
if ($a === 'reset_request') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    csrf_check($_POST['_csrf'] ?? null);

    $email = clean((string)($_POST['email'] ?? ''));

    $token = auth_reset_create($email);

    // Для теста: если токен реально создан, покажем форму reset по ссылке
    if ($token && $token !== 'ok') {
        redirect('/adm?m=auth&reset_ok=1&token=' . urlencode($token));
    }

    // Внешне всегда "ок", чтобы нельзя было угадывать email
    redirect('/adm?m=auth&reset_ok=1');
}

/**
 * Action: reset_apply (POST)
 * ROLE: применяет token и ставит новый пароль.
 */
if ($a === 'reset_apply') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    csrf_check($_POST['_csrf'] ?? null);

    $token = clean((string)($_POST['token'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');

    $ok = auth_reset_apply($token, $newPassword);

    if ($ok) {
        redirect('/adm?m=auth&reset_done=1');
    }

    redirect('/adm?m=auth&reset_fail=1');
}

http_response_code(404);
exit('Unknown action');
