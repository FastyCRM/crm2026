<?php
/**
 * FILE: /adm/index.php
 * ROLE: Shell админки (layout + подключение дизайн-системы + рендер модуля)
 * CONNECTIONS:
 *  - require_once ROOT_PATH . '/core/bootstrap.php'
 *  - меню берётся из БД (таблица modules) через core/modules.php
 *  - грузит модуль view: /modules/<m>/<m>.php
 *  - handler: /modules/<m>/assets/php/handler.php
 *  - CSS: /adm/assets/css/main.css (единственная системная точка входа)
 *  - Module CSS/JS: /modules/<m>/assets/css/<m>.css и /modules/<m>/assets/js/<m>.js (если существуют)
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/core/bootstrap.php';
require_once ROOT_PATH . '/core/modules.php';

$m = isset($_GET['m']) ? clean((string)$_GET['m']) : '';
$a = isset($_GET['a']) ? clean((string)$_GET['a']) : '';

$isAuth = auth_is_logged_in();

/**
 * Гость: разрешаем только auth
 */
if (!$isAuth) {
    if ($m === '') $m = 'auth';
    if ($m !== 'auth') {
        redirect('/adm/index.php?m=auth');
    }
}

/**
 * Авторизован: дефолт dashboard
 */
if ($isAuth && $m === '') {
    $m = 'dashboard';
}

/**
 * Защита: если модуль отключён в БД — 404 (и view, и handler)
 * auth оставляем как системный (но он тоже у тебя в БД enabled=1).
 */
if ($m !== 'auth' && $m !== '') {
    if (!module_is_enabled($m)) {
        http_response_code(404);
        exit('Module disabled');
    }
}

/**
 * Handler
 */
if ($a !== '') {
    $handler = ROOT_PATH . '/modules/' . $m . '/assets/php/handler.php';
    if (!is_file($handler)) {
        http_response_code(404);
        exit('Handler not found');
    }
    require_once $handler;
    exit;
}

/**
 * View
 */
$view = ROOT_PATH . '/modules/' . $m . '/' . $m . '.php';
if (!is_file($view)) {
    http_response_code(404);
    exit('Module view not found');
}

$theme = (string)auth_user_theme();
if ($theme === '') $theme = 'dark';

/* backward compat: если в БД лежит color — считаем это soft */
if ($theme === 'color') $theme = 'soft';

$csrf  = csrf_token();

/**
 * Меню (ТОЛЬКО из БД)
 */
$menuItems = [];
if ($isAuth) {
    $userRole = auth_user_role(); // admin|manager|user
    $menuItems = modules_get_menu($userRole);
}

/**
 * Helpers: проверить наличие модульных ассетов, чтобы не стрелять 404
 */
$moduleCssFs  = ROOT_PATH . '/modules/' . $m . '/assets/css/' . $m . '.css';
$moduleCssUrl = '/modules/' . $m . '/assets/css/' . $m . '.css';

$moduleJsFs   = ROOT_PATH . '/modules/' . $m . '/assets/js/' . $m . '.js';
$moduleJsUrl  = '/modules/' . $m . '/assets/js/' . $m . '.js';

?>
<!doctype html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CRM2026 / Admin</title>

  <link rel="stylesheet" href="/adm/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/adm/assets/css/main.css">

  <?php if (is_file($moduleCssFs)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($moduleCssUrl, ENT_QUOTES) ?>">
  <?php endif; ?>
</head>

<body class="<?= $isAuth ? 'is-auth' : 'is-guest' ?>">
<?php if ($isAuth): ?>
  <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">

  <header class="topbar">
    <label for="nav-toggle" class="burger" aria-label="Меню">
      <span></span><span></span><span></span>
    </label>
    <div class="topbar-title">CRM2026</div>
  </header>

  <div class="adm-shell">
    <aside class="sidebar">
      <div class="sidebar-top">
        <div class="brand">
          <span class="brand-badge" aria-hidden="true"></span>
          <span>CRM2026</span>
        </div>

        <nav class="menu">
          <?php foreach ($menuItems as $item): ?>
            <?php
              $active = ($m === $item['code']);
              $icon   = (string)($item['icon'] ?? 'bi-dot');
              $igroup = (string)($item['icon_group'] ?? 'neutral');
            ?>
            <a class="menu-item <?= $active ? 'active' : '' ?>"
               href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"
               data-igroup="<?= htmlspecialchars($igroup, ENT_QUOTES) ?>">
              <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES) ?> menu-icon"></i>
              <span class="menu-title"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="sidebar-bottom">
        <form class="theme-box" method="post" action="/adm/index.php?m=auth&a=set_theme">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
          <label class="theme-label">Тема</label>

          <select name="theme" class="select" onchange="this.form.submit()">
            <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Светлая</option>
            <option value="dark"  <?= $theme === 'dark'  ? 'selected' : '' ?>>Тёмная</option>
            <option value="color" <?= $theme === 'soft'  ? 'selected' : '' ?>>Цветная</option>
          </select>
        </form>

        <a class="btn danger w100" href="/adm/index.php?m=auth&a=logout">Выход</a>
      </div>
    </aside>

    <main class="main">
      <?php require_once $view; ?>
    </main>
  </div>

<?php else: ?>
  <main class="guest-main">
    <?php require_once $view; ?>
  </main>
<?php endif; ?>

  <script src="/adm/assets/js/main.js"></script>
  <script src="/adm/assets/js/adm.js"></script>

  <?php if (is_file($moduleJsFs)): ?>
    <script src="<?= htmlspecialchars($moduleJsUrl, ENT_QUOTES) ?>"></script>
  <?php endif; ?>
</body>
</html>
