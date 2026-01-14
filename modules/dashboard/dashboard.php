<?php
/**
 * FILE: /modules/dashboard/dashboard.php
 * ROLE: Главная страница админки (минимальный dashboard)
 */

declare(strict_types=1);

$allowed = module_allowed_roles('dashboard');
acl_guard($allowed);

$uid = auth_user_id();

$st = db()->prepare('SELECT email, phone, ui_theme FROM users WHERE id = :id LIMIT 1');
$st->execute([':id' => $uid]);
$u = $st->fetch() ?: [];

?>
<section class="card">
  <h1>Главная</h1>
  <p class="muted">Добро пожаловать в CRM2026.</p>

  <div class="grid">
    <div class="card mini">
      <div class="k">Пользователь</div>
      <div class="v"><?= htmlspecialchars((string)($u['email'] ?? '')) ?></div>
    </div>

    <div class="card mini">
      <div class="k">Телефон</div>
      <div class="v"><?= htmlspecialchars((string)($u['phone'] ?? '')) ?></div>
    </div>

    <div class="card mini">
      <div class="k">Тема</div>
      <div class="v"><?= htmlspecialchars((string)($u['ui_theme'] ?? '')) ?></div>
    </div>
  </div>
</section>
