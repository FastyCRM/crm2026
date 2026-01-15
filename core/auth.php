<?php
/**
 * FILE: /core/auth.php
 * ROLE:
 *  - Логин по телефону + паролю
 *  - Remember-cookie с ротацией
 *  - Восстановление пароля по email (токены)
 *  - Индивидуальная тема пользователя (users.ui_theme)
 */

declare(strict_types=1);

/* ---------- БАЗА ---------- */

/**
 * ID текущего пользователя или null.
 */
function auth_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

/**
 * Авторизован ли пользователь.
 */
function auth_is_logged_in(): bool
{
    return auth_user_id() !== null;
}

/* ---------- ТЕМА ---------- */

/**
 * Возвращает тему текущего пользователя из БД.
 * Если не авторизован — "dark".
 */
function auth_user_theme(): string
{
    $uid = auth_user_id();
    if (!$uid) return 'dark';

    $st = db()->prepare('SELECT ui_theme FROM users WHERE id = :id LIMIT 1');
    $st->execute([':id' => $uid]);
    $row = $st->fetch();

    $t = is_array($row) && isset($row['ui_theme']) ? (string)$row['ui_theme'] : 'dark';
    return in_array($t, ['light','dark','color'], true) ? $t : 'dark';
}

/**
 * Устанавливает тему пользователю (индивидуально).
 */
function auth_set_user_theme(int $userId, string $theme): bool
{
    if (!in_array($theme, ['light','dark','color'], true)) return false;

    $st = db()->prepare('UPDATE users SET ui_theme = :t WHERE id = :id');
    $st->execute([':t' => $theme, ':id' => $userId]);
    return true;
}

/* ---------- ТЕЛЕФОН ---------- */

/**
 * Нормализует телефон в формат "только цифры".
 * Пример: "+7 (999) 123-45-67" -> "79991234567"
 */
function phone_normalize(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    return $digits;
}

/* ---------- АНТИ-БРУТ ---------- */

/**
 * Ключ попыток логина: phoneDigits:ip
 */
function auth_attempt_key_phone(string $phoneDigits): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return $phoneDigits . ':' . $ip;
}

/**
 * Проверка lock.
 */
function auth_is_locked(string $key): bool
{
    $st = db()->prepare('SELECT lock_until FROM login_attempts WHERE key_str = :k LIMIT 1');
    $st->execute([':k' => $key]);
    $row = $st->fetch();

    if (!is_array($row) || empty($row['lock_until'])) return false;
    return strtotime((string)$row['lock_until']) > time();
}

/**
 * Запись неудачной попытки и постановка lock_until при превышении лимита.
 */
function auth_register_fail(string $key, int $maxAttempts, int $lockMinutes): void
{
    db()->prepare(
        'INSERT INTO login_attempts(key_str, attempts, last_try_at, lock_until)
         VALUES(:k, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
           attempts = attempts + 1,
           last_try_at = NOW(),
           lock_until = IF(attempts + 1 >= :maxA, DATE_ADD(NOW(), INTERVAL :lockM MINUTE), lock_until)'
    )->execute([
        ':k' => $key,
        ':maxA' => $maxAttempts,
        ':lockM' => $lockMinutes,
    ]);
}

/**
 * Очистка попыток при успешном логине.
 */
function auth_clear_fail(string $key): void
{
    $st = db()->prepare('DELETE FROM login_attempts WHERE key_str = :k');
    $st->execute([':k' => $key]);
}

/* ---------- REMEMBER COOKIE ---------- */

/**
 * Выдаёт remember-cookie и пишет в auth_sessions.
 * Cookie хранит selector:validator, в БД хранится hash(validator).
 */
function auth_issue_remember(int $userId): void
{
    $cfg = require ROOT_PATH . '/core/config.php';
    $life = (int)$cfg['security']['remember_lifetime_sec'];

    $selector = bin2hex(random_bytes(12));     // 24 chars
    $validator = bin2hex(random_bytes(32));    // 64 chars
    $validatorHash = hash('sha256', $validator);
    $expires = time() + $life;

    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: null;
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $st = db()->prepare(
        'INSERT INTO auth_sessions(user_id, selector, validator_hash, ip, user_agent, expires_at)
         VALUES(:uid, :sel, :vh, :ip, :ua, FROM_UNIXTIME(:exp))'
    );
    $st->execute([
        ':uid' => $userId,
        ':sel' => $selector,
        ':vh'  => $validatorHash,
        ':ip'  => $ip,
        ':ua'  => $ua,
        ':exp' => $expires,
    ]);

    setcookie('remember', $selector . ':' . $validator, [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Отзывает remember-cookie (revoked_at) и удаляет cookie.
 */
function auth_revoke_remember(): void
{
    $cookie = $_COOKIE['remember'] ?? '';
    if (is_string($cookie) && strpos($cookie, ':') !== false) {
        [$selector] = explode(':', $cookie, 2);
        if ($selector) {
            db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE selector = :s')
              ->execute([':s' => $selector]);
        }
    }

    setcookie('remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Восстановление сессии по remember-cookie.
 * При успехе ротируем: старую запись revoke, выдаём новую.
 */
function auth_restore_from_cookie(): void
{
    if (auth_is_logged_in()) return;

    $cookie = $_COOKIE['remember'] ?? '';
    if (!is_string($cookie) || strpos($cookie, ':') === false) return;

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!$selector || !$validator) return;

    $st = db()->prepare(
        'SELECT id, user_id, validator_hash, expires_at, revoked_at
         FROM auth_sessions WHERE selector = :s LIMIT 1'
    );
    $st->execute([':s' => $selector]);
    $row = $st->fetch();

    if (!is_array($row)) return;
    if (!empty($row['revoked_at'])) return;
    if (strtotime((string)$row['expires_at']) < time()) return;

    $calc = hash('sha256', $validator);
    if (!hash_equals((string)$row['validator_hash'], $calc)) {
        db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE id = :id')
          ->execute([':id' => (int)$row['id']]);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['user_id'];

    db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE id = :id')
      ->execute([':id' => (int)$row['id']]);

    auth_issue_remember((int)$row['user_id']);
}

/* ---------- ЛОГИН / ЛОГАУТ ---------- */

/**
 * Логин по телефону + паролю.
 * Телефон нормализуется до "цифры" и ищется в users.phone.
 */
function auth_login_by_phone(string $phone, string $password): bool
{
    $cfg = require ROOT_PATH . '/core/config.php';

    $phoneDigits = phone_normalize($phone);
    if ($phoneDigits === '') return false;

    $key = auth_attempt_key_phone($phoneDigits);
    if (auth_is_locked($key)) return false;

    $st = db()->prepare('SELECT id, pass_hash, status FROM users WHERE phone = :p LIMIT 1');
    $st->execute([':p' => $phoneDigits]);
    $u = $st->fetch();

    $ok = $u
        && ((string)($u['status'] ?? '') === 'active')
        && password_verify($password, (string)($u['pass_hash'] ?? ''));

    if (!$ok) {
        auth_register_fail($key, (int)$cfg['security']['login_max_attempts'], (int)$cfg['security']['login_lock_minutes']);
        return false;
    }

    auth_clear_fail($key);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    auth_issue_remember((int)$u['id']);
    return true;
}

/**
 * Выход.
 */
function auth_logout(): void
{
    auth_revoke_remember();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/* ---------- ВОССТАНОВЛЕНИЕ ПАРОЛЯ ПО EMAIL ---------- */

/**
 * Создаёт токен сброса пароля по email.
 * Возвращает "сырое" значение токена (его нужно отправить письмом пользователю).
 *
 * ВАЖНО:
 * - В БД пишем только hash(token)
 * - На практике токен отправляется ссылкой вида:
 *   https://site.tld/adm?m=auth&a=reset_form&token=XXXX
 */
function auth_reset_create(string $email): ?string
{
    $cfg = require ROOT_PATH . '/core/config.php';

    $email = mb_strtolower(trim($email));
    if ($email === '') return null;

    // Найти пользователя по email
    $st = db()->prepare('SELECT id, status FROM users WHERE email = :e LIMIT 1');
    $st->execute([':e' => $email]);
    $u = $st->fetch();

    // Не раскрываем, существует ли email (против enumeration).
    if (!is_array($u) || (string)($u['status'] ?? '') !== 'active') {
        return 'ok'; // внешне говорим "ок"
    }

    $uid = (int)$u['id'];

    // Сырый токен
    $token = bin2hex(random_bytes(32));        // 64 chars
    $tokenHash = hash('sha256', $token);

    $mins = (int)$cfg['security']['reset_token_lifetime_min'];
    $expires = time() + ($mins * 60);

    // Можно удалить старые неиспользованные токены этого юзера
    db()->prepare('DELETE FROM password_resets WHERE user_id = :uid AND used_at IS NULL')
      ->execute([':uid' => $uid]);

    db()->prepare(
        'INSERT INTO password_resets(user_id, token_hash, expires_at)
         VALUES(:uid, :th, FROM_UNIXTIME(:exp))'
    )->execute([
        ':uid' => $uid,
        ':th'  => $tokenHash,
        ':exp' => $expires,
    ]);

    return $token;
}

/**
 * Проверяет токен сброса и устанавливает новый пароль.
 * Возвращает true при успехе.
 */
function auth_reset_apply(string $token, string $newPassword): bool
{
    $token = trim($token);
    if ($token === '' || strlen($newPassword) < 6) {
        return false;
    }

    $tokenHash = hash('sha256', $token);

    // Ищем активный токен
    $st = db()->prepare(
        'SELECT id, user_id, expires_at, used_at
         FROM password_resets
         WHERE token_hash = :th
         LIMIT 1'
    );
    $st->execute([':th' => $tokenHash]);
    $r = $st->fetch();

    if (!is_array($r)) return false;
    if (!empty($r['used_at'])) return false;
    if (strtotime((string)$r['expires_at']) < time()) return false;

    $uid = (int)$r['user_id'];

    // Ставим новый пароль
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET pass_hash = :h WHERE id = :uid')
      ->execute([':h' => $hash, ':uid' => $uid]);

    // Помечаем токен использованным
    db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
      ->execute([':id' => (int)$r['id']]);

    // На всякий случай: отзываем все remember sessions пользователя (безопаснее)
    db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = :uid')
      ->execute([':uid' => $uid]);

    return true;
}

function auth_user_role(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $uid = auth_user_id();
    if (!$uid) return 'guest';

    $pdo = db(); // или pdo()

    $sql = "SELECT r.code
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY r.sort ASC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$uid]);
    $role = (string)($st->fetchColumn() ?: 'user');

    $role = trim($role);
    if (!in_array($role, ['admin','manager','user'], true)) $role = 'user';

    // можно обновлять в сессии для UI
    $_SESSION['role'] = $role;

    return $cached = $role;
}
