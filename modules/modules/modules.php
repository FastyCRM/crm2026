<?php
/**
 * FILE: /modules/modules/modules.php
 * ROLE: Панель включения/выключения модулей (DB-only, только admin)
 * CONNECTIONS:
 *  - core/bootstrap.php (подключен из /adm/index.php)
 *  - db(), csrf_token()
 *  - module_allowed_roles('modules') из /core/modules.php (берёт роли из БД)
 *  - acl_guard() из /core/acl.php
 */

declare(strict_types=1);

$uid = auth_user_id();
if (!$uid) {
    http_response_code(401);
    exit('Unauthorized');
}

$allowed = module_allowed_roles('modules');
acl_guard($allowed);

$pdo = db();

$stmt = $pdo->prepare("
    SELECT id, code, name, icon, sort, enabled, menu, roles, has_settings
    FROM modules
    ORDER BY sort ASC, id ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = csrf_token();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

/** @return array<int,string> */
function roles_from_json(?string $json): array
{
    if ($json === null) return [];
    $json = trim($json);
    if ($json === '') return [];
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Проверка: модуль существует на диске (папка + view файл).
 */
function module_files_exist(string $code): bool
{
    $dir  = ROOT_PATH . '/modules/' . $code;
    $view = $dir . '/' . $code . '.php';
    return is_dir($dir) && is_file($view);
}
?>

<div class="card">
  <div class="card__head">
    <div class="stack">
      <div class="title">Модули</div>
      <small class="muted">
        Модули регистрируются вручную в БД. Здесь только включение/выключение (<code>enabled</code>).
      </small>
    </div>
  </div>

  <div class="card__body stack">

    <div class="notice">
      <small>
        ⚠️ Важно: выключение модуля убирает его из системы (и из меню, и из доступа).
        Файлы на диске не трогаются.
      </small>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>sort</th>
          <th>code</th>
          <th>name</th>
          <th>enabled</th>
          <th>menu</th>
          <th>roles</th>
          <th></th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php
          $code    = (string)$row['code'];
          $roles   = roles_from_json($row['roles'] ?? null);
          $enabled = (int)($row['enabled'] ?? 0) === 1;

          $existsOnDisk = module_files_exist($code);
        ?>
        <tr>
          <td><?= (int)($row['sort'] ?? 0) ?></td>
          <td>
            <code><?= h($code) ?></code>
            <?php if (!$existsOnDisk): ?>
              <br><small class="muted">missing files</small>
            <?php endif; ?>
          </td>
          <td><?= h((string)($row['name'] ?? $code)) ?></td>
          <td><?= $enabled ? 'ON' : 'OFF' ?></td>
          <td><?= (int)($row['menu'] ?? 0) === 1 ? 'menu' : '—' ?></td>
          <td><small><?= h(implode(', ', $roles)) ?></small></td>
          <td class="right">
            <?php if ($code === 'auth' || $code === 'modules'): ?>
              <button class="btn sm ghost" type="button" disabled>Защищено</button>

            <?php elseif (!$existsOnDisk && !$enabled): ?>
              <button class="btn sm ghost" type="button" disabled>Нет файлов</button>

            <?php else: ?>
              <form method="post" action="/adm/index.php?m=modules&a=toggle">
                <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="code" value="<?= h($code) ?>">
                <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">

                <?php if ($enabled): ?>
                  <button class="btn danger sm" type="submit">Выключить</button>
                <?php else: ?>
                  <button class="btn primary sm" type="submit">Включить</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>
