<?php
/**
 * FILE: /modules/users/lib/service.php
 * ROLE: сервис пользователей (users_* функции) под схему:
 *  - users(id,email,phone,pass_hash,name,status,ui_theme,created_at,updated_at)
 *  - roles(id,code,name,sort)
 *  - user_roles(user_id,role_id)
 *
 * RULES:
 *  - совместимость PHP 7.4
 *  - транзакции НЕ должны быть вложенными: внутренние функции уважают уже открытую транзакцию
 *  - ошибки пробрасываем наверх (handler/audit/лог решают что делать)
 */

declare(strict_types=1);

/**
 * Получить PDO из проекта (db() или pdo()).
 */
function users_pdo(): PDO
{
    if (function_exists('db')) return db();
    if (function_exists('pdo')) return pdo();
    throw new RuntimeException('PDO getter not found (db() or pdo())');
}

/**
 * ===== Transaction helpers (anti-nested) =====
 * Возвращают флаг $owned = true, если транзакцию открыли МЫ.
 */
function users_tx_begin(PDO $pdo): bool
{
    if ($pdo->inTransaction()) {
        return false; // транзакция уже есть, мы ей не владеем
    }
    $pdo->beginTransaction();
    return true;
}

function users_tx_commit(PDO $pdo, bool $owned): void
{
    if ($owned && $pdo->inTransaction()) {
        $pdo->commit();
    }
}

function users_tx_rollback(PDO $pdo, bool $owned): void
{
    if ($owned && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

/**
 * Хешировать пароль.
 */
function users_hash_password(string $password): string
{
    $password = trim($password);
    if ($password === '') throw new InvalidArgumentException('Empty password');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    if (!is_string($hash) || $hash === '') throw new RuntimeException('Password hash failed');

    return $hash;
}


function users_normalize_phone(string $phone): string
{
    $p = preg_replace('/\D+/', '', $phone); // только цифры
    $p = (string)$p;

    // РФ-логика: 8XXXXXXXXXX -> 7XXXXXXXXXX
    if (strlen($p) === 11 && $p[0] === '8') {
        $p = '7' . substr($p, 1);
    }

    return $p;
}




/**
 * Получить role_id по code (admin/manager/user)
 */
function users_role_id_by_code(string $code): int
{
    $code = trim($code);
    if (!in_array($code, ['admin','manager','user'], true)) $code = 'user';

    $pdo = users_pdo();
    $st = $pdo->prepare("SELECT id FROM roles WHERE code=? LIMIT 1");
    $st->execute([$code]);

    $id = (int)($st->fetchColumn() ?: 0);
    if ($id <= 0) throw new RuntimeException("Role not found: {$code}");

    return $id;
}

/**
 * Установить роль пользователю (перезаписать user_roles).
 * ВАЖНО: уважает внешнюю транзакцию (не делает beginTransaction внутри beginTransaction).
 */
function users_set_role(int $userId, string $roleCode): void
{
    if ($userId <= 0) throw new InvalidArgumentException('id required');

    $roleId = users_role_id_by_code($roleCode);
    $pdo = users_pdo();

    $owned = users_tx_begin($pdo);
    try {
        $pdo->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$userId]);
        $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);

        users_tx_commit($pdo, $owned);
    } catch (Throwable $e) {
        users_tx_rollback($pdo, $owned);
        throw $e;
    }
}

/**
 * Список пользователей с их role_code
 * @return array<int,array<string,mixed>>
 */
function users_list_all(): array
{
    $pdo = users_pdo();
    $sql = "SELECT 
              u.id, u.email, u.phone, u.name, u.status, u.ui_theme, u.created_at, u.updated_at,
              COALESCE(r.code,'user') AS role
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            ORDER BY u.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function users_get(int $id): ?array
{
    if ($id <= 0) return null;

    $pdo = users_pdo();
    $sql = "SELECT 
              u.id, u.email, u.phone, u.name, u.status, u.ui_theme, u.created_at, u.updated_at,
              COALESCE(r.code,'user') AS role
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.id=?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$id]);

    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * Создать пользователя + назначить роль.
 * data: email, phone, name, status, ui_theme, password, role
 */
function users_create(array $data): int
{
    $email   = trim((string)($data['email'] ?? ''));
    $phone = users_normalize_phone((string)($data['phone'] ?? ''));
    $name    = trim((string)($data['name'] ?? ''));
    $status  = (string)($data['status'] ?? 'active');
    $uiTheme = (string)($data['ui_theme'] ?? 'dark');
    $role    = (string)($data['role'] ?? 'user');
    $password = (string)($data['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('invalid email');
    }
    if ($phone === '') {
        throw new InvalidArgumentException('phone required');
    }
    if (!in_array($status, ['active','blocked'], true)) $status = 'active';
    if (!in_array($uiTheme, ['light','dark','color'], true)) $uiTheme = 'dark';

    $passHash = $password !== ''
        ? users_hash_password($password)
        : users_hash_password(bin2hex(random_bytes(6)));

    $pdo = users_pdo();

    // уникальность email/phone
    $st0 = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st0->execute([$email]);
    if ($st0->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('email already exists');

    $st1 = $pdo->prepare("SELECT id FROM users WHERE phone=? LIMIT 1");
    $st1->execute([$phone]);
    if ($st1->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('phone already exists');

    $owned = users_tx_begin($pdo);
    try {
        $sql = "INSERT INTO users (email, phone, pass_hash, name, status, ui_theme, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $st = $pdo->prepare($sql);
        $st->execute([$email, $phone, $passHash, ($name !== '' ? $name : null), $status, $uiTheme]);

        $newId = (int)$pdo->lastInsertId();

        // ВАЖНО: users_set_role теперь не делает вложенный beginTransaction
        users_set_role($newId, $role);

        users_tx_commit($pdo, $owned);
        return $newId;
    } catch (Throwable $e) {
        users_tx_rollback($pdo, $owned);
        throw $e;
    }
}

function users_update(int $id, array $data): void
{
    if ($id <= 0) throw new InvalidArgumentException('id required');

    $email   = trim((string)($data['email'] ?? ''));
    $phone = users_normalize_phone((string)($data['phone'] ?? ''));
    $name    = trim((string)($data['name'] ?? ''));
    $status  = (string)($data['status'] ?? 'active');
    $uiTheme = (string)($data['ui_theme'] ?? 'dark');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('invalid email');
    }
    if ($phone === '') throw new InvalidArgumentException('phone required');
    if (!in_array($status, ['active','blocked'], true)) $status = 'active';
    if (!in_array($uiTheme, ['light','dark','color'], true)) $uiTheme = 'dark';

    $pdo = users_pdo();

    $current = users_get($id);
    if (!$current) throw new RuntimeException('user not found');

    // уникальность при смене email/phone
    if ($email !== (string)$current['email']) {
        $st0 = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $st0->execute([$email]);
        if ($st0->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('email already exists');
    }
    if ($phone !== (string)$current['phone']) {
        $st1 = $pdo->prepare("SELECT id FROM users WHERE phone=? LIMIT 1");
        $st1->execute([$phone]);
        if ($st1->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('phone already exists');
    }

    $sql = "UPDATE users
            SET email=?, phone=?, name=?, status=?, ui_theme=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$email, $phone, ($name !== '' ? $name : null), $status, $uiTheme, $id]);
}

function users_set_password(int $id, string $password): void
{
    if ($id <= 0) throw new InvalidArgumentException('id required');

    $hash = users_hash_password($password);
    $pdo = users_pdo();

    $st = $pdo->prepare("UPDATE users SET pass_hash=?, updated_at=CURRENT_TIMESTAMP WHERE id=? LIMIT 1");
    $st->execute([$hash, $id]);
}

/**
 * Удаление пользователя (в твоей БД FK CASCADE на user_roles/auth_sessions etc уже есть)
 */
function users_delete(int $id): void
{
    if ($id <= 0) throw new InvalidArgumentException('id required');

    $pdo = users_pdo();
    $st = $pdo->prepare("DELETE FROM users WHERE id=? LIMIT 1");
    $st->execute([$id]);
}
