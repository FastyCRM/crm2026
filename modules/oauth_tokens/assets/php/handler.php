<?php
/**
 * FILE: /modules/oauth_tokens/assets/php/handler.php
 * ROLE: Handler для oauth_tokens (CRUD + OAuth start/callback)
 * CONNECTIONS:
 *  - db()
 *  - auth_user_id(), auth_user_role()
 *  - module_allowed_roles(), acl_guard()
 *  - csrf_check()
 *  - clean()
 *
 * RULES:
 *  - manager запрещён
 *  - admin: CRUD + назначение + start/callback
 *  - user: только start/callback для назначенного ему токена
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) { http_response_code(403); exit('Direct access forbidden'); }

$uid = auth_user_id();
if (!$uid) { http_response_code(401); exit('Unauthorized'); }

$role = auth_user_role();
acl_guard(['admin','user']); // менеджера не пускаем

$allowed = module_allowed_roles('oauth_tokens');
acl_guard($allowed);

$pdo = db();
$a = isset($_GET['a']) ? clean((string)$_GET['a']) : '';

function jexit(array $j, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_admin(): bool { return auth_user_role() === 'admin'; }

function oauth_redirect_uri(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/adm/index.php?m=oauth_tokens&a=callback';
}

function oauth_get_token(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM oauth_tokens WHERE id = :id");
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function oauth_user_token_id(PDO $pdo, int $uid): int {
    $st = $pdo->prepare("SELECT oauth_token_id FROM oauth_token_users WHERE user_id = :uid");
    $st->execute([':uid' => $uid]);
    return (int)($st->fetchColumn() ?: 0);
}

function oauth_user_can_manage(PDO $pdo, int $tokenId, int $uid): bool {
    if (is_admin()) return true;
    $my = oauth_user_token_id($pdo, $uid);
    return $my > 0 && $my === $tokenId;
}

function http_post_form(string $url, array $fields): array {
    $ch = curl_init($url);
    if (!$ch) return ['_ok'=>false,'_error'=>'curl_init failed'];

    $payload = http_build_query($fields);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) return ['_ok'=>false,'_http'=>$code,'_error'=>$err ?: 'curl error'];

    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return ['_ok'=>false,'_http'=>$code,'_error'=>'bad json','_raw'=>(string)$raw];

    $j['_ok'] = ($code >= 200 && $code < 300);
    $j['_http'] = $code;
    return $j;
}

/**
 * OAuth START / CALLBACK — это redirect/popup flow, НЕ JSON.
 */
if ($a === 'start') {
    csrf_check($_POST['_csrf'] ?? null);

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); exit('Bad id'); }

    if (!oauth_user_can_manage($pdo, $id, $uid)) { http_response_code(403); exit('Forbidden'); }

    $row = oauth_get_token($pdo, $id);
    if (!$row) { http_response_code(404); exit('Not found'); }

    $clientId = (string)($row['client_id'] ?? '');
    if ($clientId === '') { http_response_code(400); exit('Empty client_id'); }

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $state = bin2hex(random_bytes(16));
    $popup = (int)($_POST['popup'] ?? 0);

    $_SESSION['oauth_tokens_state'] = [
        'state' => $state,
        'oauth_token_id' => $id,
        'uid' => $uid,
        'ts' => time(),
        'popup' => $popup,
    ];

    $authUrl = 'https://oauth.yandex.ru/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => oauth_redirect_uri(),
        'state' => $state,
    ]);

    header('Location: ' . $authUrl, true, 302);
    exit;
}

if ($a === 'callback') {
    $code  = isset($_GET['code']) ? (string)$_GET['code'] : '';
    $state = isset($_GET['state']) ? (string)$_GET['state'] : '';

    if ($code === '' || $state === '') {
        header('Location: /adm/index.php?m=oauth_tokens&err=bad_callback', true, 302);
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $st = $_SESSION['oauth_tokens_state'] ?? null;
    unset($_SESSION['oauth_tokens_state']);

    if (!is_array($st) || ($st['state'] ?? '') !== $state) {
        header('Location: /adm/index.php?m=oauth_tokens&err=bad_state', true, 302);
        exit;
    }

    $tokenId = (int)($st['oauth_token_id'] ?? 0);
    $expectedUid = (int)($st['uid'] ?? 0);
    $wasPopup = (int)($st['popup'] ?? 0);

    if ($tokenId <= 0 || $expectedUid !== $uid) {
        header('Location: /adm/index.php?m=oauth_tokens&err=wrong_user', true, 302);
        exit;
    }

    if (!oauth_user_can_manage($pdo, $tokenId, $uid)) {
        header('Location: /adm/index.php?m=oauth_tokens&err=forbidden', true, 302);
        exit;
    }

    $row = oauth_get_token($pdo, $tokenId);
    if (!$row) {
        header('Location: /adm/index.php?m=oauth_tokens&err=not_found', true, 302);
        exit;
    }

    $clientId = (string)($row['client_id'] ?? '');
    $clientSecret = (string)($row['client_secret'] ?? '');
    if ($clientId === '' || $clientSecret === '') {
        header('Location: /adm/index.php?m=oauth_tokens&err=empty_client', true, 302);
        exit;
    }

    $resp = http_post_form('https://oauth.yandex.ru/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    if (!($resp['_ok'] ?? false)) {
        header('Location: /adm/index.php?m=oauth_tokens&err=token_exchange', true, 302);
        exit;
    }

    $accessToken = (string)($resp['access_token'] ?? '');
    if ($accessToken === '') {
        header('Location: /adm/index.php?m=oauth_tokens&err=no_access_token', true, 302);
        exit;
    }

    $stUp = $pdo->prepare("
        UPDATE oauth_tokens
        SET access_token = :t,
            token_received_at = NOW(),
            updated_at = NOW(),
            updated_by = :uid
        WHERE id = :id
    ");
    $stUp->execute([':t'=>$accessToken, ':uid'=>$uid, ':id'=>$tokenId]);

    // POPUP MODE: закрыть окно + обновить opener
    if ($wasPopup === 1) {
        header('Content-Type: text/html; charset=utf-8');
        $url = '/adm/index.php?m=oauth_tokens&ok=1';
        echo '<!doctype html><html><head><meta charset="utf-8"><title>OAuth OK</title></head><body>';
        echo '<script>';
        echo 'try { if (window.opener) { window.opener.location.href = ' . json_encode($url) . '; } } catch(e) {}';
        echo 'window.close();';
        echo '</script>';
        echo 'Токен сохранён. Можно закрыть это окно.';
        echo '</body></html>';
        exit;
    }

    header('Location: /adm/index.php?m=oauth_tokens&ok=1', true, 302);
    exit;
}

/**
 * Ниже — JSON CRUD (admin-only).
 */
csrf_check($_POST['_csrf'] ?? null);

if ($a === 'create') {
    if (!is_admin()) jexit(['ok'=>false,'error'=>'Forbidden'], 403);

    $name = clean((string)($_POST['name'] ?? ''));
    $clientId = clean((string)($_POST['client_id'] ?? ''));
    $clientSecret = clean((string)($_POST['client_secret'] ?? ''));
    $assignUid = (int)($_POST['assign_user_id'] ?? 0);

    if ($name==='' || $clientId==='' || $clientSecret==='') {
        jexit(['ok'=>false,'error'=>'Empty fields'], 400);
    }

    $stIns = $pdo->prepare("
        INSERT INTO oauth_tokens (name, client_id, client_secret, access_token, token_received_at, updated_at, updated_by)
        VALUES (:name, :cid, :csec, NULL, NULL, NOW(), :uid)
    ");
    $stIns->execute([':name'=>$name, ':cid'=>$clientId, ':csec'=>$clientSecret, ':uid'=>$uid]);
    $newId = (int)$pdo->lastInsertId();

    if ($assignUid > 0) {
        $pdo->prepare("DELETE FROM oauth_token_users WHERE oauth_token_id = :tid")->execute([':tid'=>$newId]);
        $pdo->prepare("INSERT INTO oauth_token_users (oauth_token_id, user_id) VALUES (:tid,:uid)")
            ->execute([':tid'=>$newId, ':uid'=>$assignUid]);
    }

    jexit(['ok'=>true,'id'=>$newId]);
}

if ($a === 'update') {
    if (!is_admin()) jexit(['ok'=>false,'error'=>'Forbidden'], 403);

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jexit(['ok'=>false,'error'=>'Bad id'], 400);

    $name = clean((string)($_POST['name'] ?? ''));
    $clientId = clean((string)($_POST['client_id'] ?? ''));
    $clientSecret = clean((string)($_POST['client_secret'] ?? ''));
    $assignUid = (int)($_POST['assign_user_id'] ?? 0);

    if ($name==='' || $clientId==='' || $clientSecret==='') {
        jexit(['ok'=>false,'error'=>'Empty fields'], 400);
    }

    $stUp = $pdo->prepare("
        UPDATE oauth_tokens
        SET name=:name, client_id=:cid, client_secret=:csec, updated_at=NOW(), updated_by=:uid
        WHERE id=:id
    ");
    $stUp->execute([':name'=>$name, ':cid'=>$clientId, ':csec'=>$clientSecret, ':uid'=>$uid, ':id'=>$id]);

    $pdo->prepare("DELETE FROM oauth_token_users WHERE oauth_token_id = :tid")->execute([':tid'=>$id]);
    if ($assignUid > 0) {
        $pdo->prepare("INSERT INTO oauth_token_users (oauth_token_id, user_id) VALUES (:tid,:uid)")
            ->execute([':tid'=>$id, ':uid'=>$assignUid]);
    }

    jexit(['ok'=>true]);
}

if ($a === 'delete') {
    if (!is_admin()) jexit(['ok'=>false,'error'=>'Forbidden'], 403);

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jexit(['ok'=>false,'error'=>'Bad id'], 400);

    $pdo->prepare("DELETE FROM oauth_token_users WHERE oauth_token_id = :id")->execute([':id'=>$id]);
    $pdo->prepare("DELETE FROM oauth_tokens WHERE id = :id")->execute([':id'=>$id]);

    jexit(['ok'=>true]);
}

jexit(['ok'=>false,'error'=>'Unknown action'], 404);
