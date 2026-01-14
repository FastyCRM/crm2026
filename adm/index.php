<?php
/**
 * FILE: /adm/index.php
 * ROLE: Shell админки
 * UX:
 *  - guest (не залогинен): без sidebar, контент по центру экрана
 *  - auth (залогинен): sidebar + topbar с бургером (адаптив), тема+выход внизу
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/core/bootstrap.php';


/**
 * Собрать меню из /modules/all/settings.php
 * - берет только enabled=1 и menu=1
 * - фильтрует по roles (если roles пустой или не массив -> доступно всем)
 * - сортирует по sort ASC
 * - формирует href вида /adm/index.php?m=<module>
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
        if (strpos($icon, 'bi-') !== 0) $icon = 'bi-' . $icon;

        $items[] = [
            'code'       => $module,
            'title'      => (string)($cfg['name'] ?? $module),
            'href'       => '/adm/index.php?m=' . urlencode($module),
            'sort'       => (int)($cfg['sort'] ?? 100),
            'icon'       => $icon,
            'icon_group' => (string)($cfg['icon_group'] ?? 'neutral'),
        ];
    }

    usort($items, function ($a, $b) {
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

$theme = auth_user_theme();
$csrf  = csrf_token();

/**
 * Меню пока минимальное
 */
$menuItems = [];
if ($isAuth) {
    $userRole = auth_user_role(); // admin|manager|user
    $menuItems = adm_get_menu_modules($userRole);
}


/* FIX ICONS:
   УБРАЛИ эти строки, потому что $item тут ещё не существует:
   $icon = $item['icon'] ?? 'bi-dot';
   $igroup = $item['icon_group'] ?? 'neutral';
*/
?>
<!doctype html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>FastyCRM/Admin</title>

  <link rel="stylesheet" href="/adm/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/adm/assets/css/main.css">
  <link rel="stylesheet" href="/adm/assets/css/adm.css">

  <?php
    $moduleCss = '/modules/' . $m . '/assets/css/' . $m . '.css';
    echo '<link rel="stylesheet" href="' . htmlspecialchars($moduleCss) . '">';
  ?>
</head>

<body class="<?= $isAuth ? 'is-auth' : 'is-guest' ?>">
<?php if ($isAuth): ?>
  <!-- CSS-only бургер: чекбокс + label -->
  <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">

  <header class="topbar">
    <label for="nav-toggle" class="burger" aria-label="Меню">
      <span></span><span></span><span></span>
    </label>
    <div class="topbar-title">crm2026</div>
  </header>

  <div class="adm-shell">
    <aside class="sidebar">
      <div class="sidebar-top">
        <div class="brand">FastyCRM</div>

        <nav class="menu">
          <?php foreach ($menuItems as $item): ?>
            <?php
              $active = ($m === $item['code']);

              // FIX ICONS: считаем ИКОНКУ и ГРУППУ на каждый пункт меню
              $icon = isset($item['icon']) ? trim((string)$item['icon']) : '';
              if ($icon === '') $icon = 'bi-dot';

              // Если ты вдруг будешь хранить без "bi-" — добавим автоматически
              if (strpos($icon, 'bi-') !== 0) {
                  $icon = 'bi-' . $icon;
              }

                $igroup = $item['icon_group'] ?? 'neutral';
                $icon   = $item['icon'] ?? 'bi-dot';

            ?>

            <a class="menu-item <?= $active ? 'active' : '' ?>"
               href="<?= htmlspecialchars($item['href']) ?>"
               data-igroup="<?= htmlspecialchars($igroup) ?>">
              <i class="bi <?= htmlspecialchars($icon) ?> menu-icon"></i>

              <span class="menu-title"><?= htmlspecialchars($item['title']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="sidebar-bottom">
        <form class="theme-box" method="post" action="/adm/index.php?m=auth&a=set_theme">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
          <label class="theme-label">Тема</label>

          <select name="theme" class="theme-select" onchange="this.form.submit()">
            <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Светлая</option>
            <option value="dark"  <?= $theme === 'dark'  ? 'selected' : '' ?>>Тёмная</option>
            <option value="color" <?= $theme === 'color' ? 'selected' : '' ?>>Цветная</option>
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
  <!-- Гость: без меню, контент строго по центру -->
  <main class="guest-main">
    <?php require_once $view; ?>
  </main>
<?php endif; ?>

  <script src="/adm/assets/js/main.js"></script>
  <script src="/adm/assets/js/adm.js"></script>
  <?php
    $moduleJs = '/modules/' . $m . '/assets/js/' . $m . '.js';
    echo '<script src="' . htmlspecialchars($moduleJs) . '"></script>';
  ?>
</body>
</html>
