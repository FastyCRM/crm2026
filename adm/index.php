<?php
/**
 * FILE: /adm/index.php
 * ROLE: Shell админки (layout + подключение дизайн-системы + рендер модуля)
 * CONNECTIONS:
 *  - require_once ROOT_PATH . '/core/bootstrap.php'
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

/**
 * Собрать меню из /modules//settings.php
 *
 * @param string $userRole Текущая роль пользователя: admin|manager|user
 * @return array<int, array<string, mixed>>
 */
function adm_get_menu_modules(string $userRole): array
{
    $dir = ROOT_PATH . '/modules';
    if (!is_dir($dir)) return [];

    $items = [];
    $list = scandir($dir);
    if ($list === false) return [];

    foreach ($list as $module) {
        if ($module === '.' || $module === '..') continue;

        $modulePath = $dir . '/' . $module;
        if (!is_dir($modulePath)) continue;

        $settingsFile = $modulePath . '/settings.php';
        if (!is_file($settingsFile)) continue;

        $cfg = require $settingsFile;
        if (!is_array($cfg)) continue;

        if ((int)($cfg['enabled'] ?? 0) !== 1) continue;
        if ((int)($cfg['menu'] ?? 0) !== 1) continue;

        $roles = $cfg['roles'] ?? [];
        if (is_array($roles) && count($roles) > 0) {
            if (!in_array($userRole, $roles, true)) continue;
        }

        $icon = trim((string)($cfg['icon'] ?? 'bi-dot'));
        if ($icon === '') $icon = 'bi-dot';
        if (strpos($icon, 'bi-') !== 0) {
            $icon = 'bi-' . $icon;
        }

        $items[] = [
            'code'       => $module,
            'title'      => (string)($cfg['name'] ?? $module),
            'href'       => '/adm/index.php?m=' . urlencode($module),
            'sort'       => (int)($cfg['sort'] ?? 100),
            'icon'       => $icon,
            'icon_group' => (string)($cfg['icon_group'] ?? 'neutral'),
        ];
    }

    usort($items, static function ($a, $b) {
        return ($a['sort'] <=> $b['sort']);
    });

    return $items;
}

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
 * Меню
 */
$menuItems = [];
if ($isAuth) {
    $userRole = auth_user_role(); // admin|manager|user
    $menuItems = adm_get_menu_modules($userRole);
}

/**
 * Helpers: проверить наличие модульных ассетов, чтобы не стрелять 404
 */
$moduleCssFs = ROOT_PATH . '/modules/' . $m . '/assets/css/' . $m . '.css';
$moduleCssUrl = '/modules/' . $m . '/assets/css/' . $m . '.css';

$moduleJsFs  = ROOT_PATH . '/modules/' . $m . '/assets/js/' . $m . '.js';
$moduleJsUrl = '/modules/' . $m . '/assets/js/' . $m . '.js';

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
            <option value="soft"  <?= $theme === 'soft'  ? 'selected' : '' ?>>Цветная</option>
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
