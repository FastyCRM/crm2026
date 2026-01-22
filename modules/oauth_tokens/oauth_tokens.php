<?php
/**
 * FILE: /modules/oauth_tokens/oauth_tokens.php
 * ROLE: VIEW модуля OAuth Tokens (таблица + модалка как в users)
 * CONNECTIONS:
 *  - auth_user_id(), auth_user_role()
 *  - module_allowed_roles(), acl_guard()
 *  - db()
 *  - csrf_token()
 *
 * RULES:
 *  - ACL/menu берём только из БД (core/modules.php).
 *  - manager сюда не должен попадать.
 *  - admin: CRUD + назначение + обновление токена
 *  - user: видит только назначенный токен и может обновить его
 */

declare(strict_types=1);

$uid = auth_user_id();
if (!$uid) { http_response_code(401); exit('Unauthorized'); }

$role = auth_user_role(); // admin|manager|user
acl_guard(['admin','user']); // менеджера не пускаем вообще

$allowed = module_allowed_roles('oauth_tokens');
acl_guard($allowed);

$pdo = db();
$csrf = csrf_token();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$isAdmin = ($role === 'admin');

if ($isAdmin) {
    $st = $pdo->query("
        SELECT
            t.id, t.name, t.client_id, t.client_secret,
            t.token_received_at,
            u.user_id AS assigned_user_id,
            COALESCE(us.name, us.phone, us.email) AS assigned_user_title
        FROM oauth_tokens t
        LEFT JOIN oauth_token_users u ON u.oauth_token_id = t.id
        LEFT JOIN users us ON us.id = u.user_id
        ORDER BY t.id DESC
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $st2 = $pdo->query("
        SELECT id, COALESCE(name, phone, email) AS title
        FROM users
        ORDER BY id ASC
    ");
    $users = $st2->fetchAll(PDO::FETCH_ASSOC);
} else {
    // user видит только свой назначенный токен (1 шт)
    $st = $pdo->prepare("
        SELECT t.id, t.name, t.client_id, t.token_received_at
        FROM oauth_tokens t
        JOIN oauth_token_users u ON u.oauth_token_id = t.id
        WHERE u.user_id = :uid
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $st->execute([':uid' => $uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $users = [];
}
?>

<link rel="stylesheet" href="/modules/oauth_tokens/assets/css/oauth_tokens.css">
<script defer src="/modules/oauth_tokens/assets/js/oauth_tokens.js"></script>

<div class="page-head">
  <div class="row">
    <div class="title">OAuth токены</div>
    <div class="right">
      <?php if ($isAdmin): ?>
        <button class="btn primary" type="button" onclick="OauthTokensUI.openCreate()">Добавить</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="oauth-tokens__help muted">
    Admin: добавляет/назначает/редактирует/удаляет. User: видит только свой назначенный токен и может обновить его.
  </div>
</div>

<div class="card card--soft">
  <div class="card__body">
    <!-- общий csrf (для JS) -->
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

    <table class="table w100">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Имя</th>
          <th class="muted">client_id</th>
          <th class="muted" style="width:180px;">Токен получен</th>
          <?php if ($isAdmin): ?>
            <th class="muted" style="width:220px;">Назначен</th>
          <?php endif; ?>
          <th style="width:260px;">Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $id = (int)$r['id'];
          $assignedUid = (int)($r['assigned_user_id'] ?? 0);
          $assignedTitle = (string)($r['assigned_user_title'] ?? '');
        ?>
        <tr>
          <td><?= $id ?></td>
          <td><?= h((string)($r['name'] ?? '')) ?></td>
          <td class="muted"><code><?= h((string)($r['client_id'] ?? '')) ?></code></td>
          <td class="muted"><?= !empty($r['token_received_at']) ? h((string)$r['token_received_at']) : '—' ?></td>

          <?php if ($isAdmin): ?>
            <td class="muted">
              <?= $assignedUid > 0 ? ('#'.$assignedUid.' — '.h($assignedTitle)) : '—' ?>
            </td>
          <?php endif; ?>

          <td>
            <div class="row" style="gap:10px; flex-wrap:wrap;">
              <button class="btn sm primary" type="button" onclick="OauthTokensUI.start(<?= $id ?>)">
                Обновить токен
              </button>

              <?php if ($isAdmin): ?>
                <button class="btn sm" type="button"
                  onclick='OauthTokensUI.openEdit(<?= json_encode([
                      'id'=>$id,
                      'name'=>(string)($r['name'] ?? ''),
                      'client_id'=>(string)($r['client_id'] ?? ''),
                      'client_secret'=>(string)($r['client_secret'] ?? ''),
                      'assign_user_id'=>$assignedUid,
                    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>
                  Редактировать
                </button>

                <button class="btn sm danger" type="button" onclick="OauthTokensUI.remove(<?= $id ?>)">
                  Удалить
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr>
          <td colspan="<?= $isAdmin ? 6 : 5 ?>" class="muted">
            <?php if ($isAdmin): ?>Пока нет токенов.<?php else: ?>Токен не назначен.<?php endif; ?>
          </td>
        </tr>
      <?php endif; ?>

      </tbody>
    </table>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Модалка Create/Edit -->
<div id="oauth-modal" class="oauth-modal" style="display:none;">
  <div class="oauth-modal__backdrop" onclick="OauthTokensUI.close()"></div>
  <div class="oauth-modal__panel card">
    <div class="card__head">
      <div class="title" id="oauth-modal-title">OAuth токен</div>
      <div class="right"></div>
      <button class="btn sm ghost" type="button" onclick="OauthTokensUI.close()">Закрыть</button>
    </div>
    <div class="card__body">
      <form id="oauth-form" onsubmit="return OauthTokensUI.submit(event);">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="">

        <div class="oauth-grid-2">
          <div>
            <label>Имя</label>
            <input name="name" class="input" required>
          </div>

          <div>
            <label>Назначить пользователя</label>
            <select name="assign_user_id" class="select">
              <option value="0">— не назначать —</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>">#<?= (int)$u['id'] ?> — <?= h((string)$u['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>client_id</label>
            <input name="client_id" class="input" required>
          </div>

          <div>
            <label>client_secret</label>
            <input name="client_secret" class="input" required>
          </div>
        </div>

        <div class="oauth-actions-row">
          <button class="btn primary" type="submit">Сохранить</button>
          <button class="btn ghost" type="button" onclick="OauthTokensUI.close()">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
