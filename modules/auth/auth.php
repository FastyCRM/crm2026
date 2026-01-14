<?php
/**
 * FILE: /modules/auth/auth.php
 * ROLE:
 *  - Вход: телефон + пароль
 *  - Восстановление по email: ?page=reset
 *  - Новый пароль по token: ?page=reset&token=XXX
 *
 * IMPORTANT:
 * - После логина редирект на dashboard
 * - Выбор темы вынесен в sidebar (shell), тут его НЕТ
 */

declare(strict_types=1);

$csrf = csrf_token();
$page = isset($_GET['page']) ? clean((string)$_GET['page']) : 'login';

/**
 * Если уже залогинен — сразу на главную
 */
if (auth_is_logged_in()) {
    redirect('/adm/index.php?m=dashboard');
}

/**
 * RESET PAGE
 */
if ($page === 'reset') {
    $token = isset($_GET['token']) ? clean((string)$_GET['token']) : '';
    ?>
    <section class="card auth-card">
      <h1>Восстановление пароля</h1>

      <?php if (!empty($_GET['reset_ok'])): ?>
        <div class="notice">Если email существует, инструкция будет отправлена.</div>
      <?php endif; ?>

      <?php if (!empty($_GET['reset_fail'])): ?>
        <div class="notice danger">Токен недействителен или истёк.</div>
      <?php endif; ?>

      <?php if ($token === ''): ?>
        <form method="post" action="/adm/index.php?m=auth&a=reset_request" class="form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
          <label>Email</label>
          <input name="email" type="email" required autocomplete="email">
          <button class="btn primary" type="submit">Отправить</button>
        </form>
      <?php else: ?>
        <form method="post" action="/adm/index.php?m=auth&a=reset_apply" class="form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <label>Новый пароль</label>
          <input name="new_password" type="password" required minlength="6" autocomplete="new-password">
          <button class="btn primary" type="submit">Сменить пароль</button>
        </form>
      <?php endif; ?>

      <div class="links">
        <a href="/adm/index.php?m=auth">← Вернуться ко входу</a>
      </div>
    </section>
    <?php
    return;
}

/**
 * LOGIN PAGE
 */
?>
<section class="card auth-card">
  <h1>Вход</h1>

  <?php if (!empty($_GET['err'])): ?>
    <div class="notice danger">Неверный телефон или пароль.</div>
  <?php endif; ?>

  <form method="post" action="/adm/index.php?m=auth&a=login" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <label>Телефон</label>
    <input name="phone" type="tel" required autocomplete="username" placeholder="+7 999 123-45-67">

    <label>Пароль</label>
    <input name="password" type="password" required autocomplete="current-password">

    <button class="btn primary" type="submit">Войти</button>
  </form>

  <div class="links">
    <a href="/adm/index.php?m=auth&page=reset">Забыли пароль?</a>
  </div>
</section>
