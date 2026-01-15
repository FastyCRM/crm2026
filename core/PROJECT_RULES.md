0) Главное правило №1 (после сегодняшних косяков)
❗Я НЕ ИМЕЮ ПРАВА:

придумывать новые файлы (core/csrf.php, core_require_map.php, и т.п.) если ты их не создавал/не просил

менять названия существующих функций по своему (json_out → json_response, и т.п.)

использовать PHP 8 функции (str_contains, str_starts_with) — у нас PHP 7.4

✅ Я ОБЯЗАН:

работать строго по тем файлам и функциям, которые реально есть

если чего-то нет — использовать то, что уже есть, или дать код нового файла только если ты сказал “создай файл X”

любые новые функции/файлы — только с точным путём и названием, и это считается “контрактом”

1) “Реестр контрактов” (ядро проекта, имена фиксированы)

Это список, который я должен соблюдать 1-в-1.

Core-файлы (существующие и используемые)

/core/db.php → db() (PDO)

/core/response.php → json_out(), json_response(), redirect()

/core/security.php → clean(), csrf_token(), csrf_check() (CSRF ТУТ)

/core/auth.php → auth_user_id(), auth_user_role(), auth_login_by_phone(), phone_normalize() и т.д.

/core/acl.php → acl_guard()

/core/modules.php → меню/модули (как у тебя сделано)

/core/audit.php → audit_log(), audit_error()

/core/autoload_requires.php → автоподгрузка зависимостей (PHP 7.4)

Запрещённые “левые имена”

нельзя использовать csrf_guard() если у нас csrf_check()

нельзя использовать json_response() вместо json_out() без алиаса (ты алиас сделал — ок)

нельзя писать core:csrf в requires если нет файла core/csrf.php (CSRF в security.php)

2) PHP 7.4 правило
Запрещено:

str_contains(), str_starts_with(), str_ends_with()

Разрешено (как заменять):

strpos($s, 'x') !== false

substr($s, 0, strlen($prefix)) === $prefix

3) Правило модулей: один стандарт, без фантазии

Каждый модуль обязан иметь структуру:

/modules/<code>/
  <code>.php                 (входной файл view)
  settings.php               (метаданные)
  /assets/
    /php/handler.php         (ajax/POST обработчики)
    /css/...
    /js/...
  /lib/service.php           (сервис функции <code>_*)

settings.php — только метаданные

Минимум:

enabled/menu/name/icon/sort/roles/has_settings

Никаких “exports/requires” пока ты не скажешь, что включаем эту схему.
(Сегодня ты сказал “пока без модуль синхов” — значит так и живём.)

4) Правило подключений в handler.php (обязательный шаблон)

В каждом handler’е:

✅ Подключаем только существующее:
require_once ROOT_PATH . '/core/response.php';
require_once ROOT_PATH . '/core/security.php';
require_once ROOT_PATH . '/core/auth.php';
require_once ROOT_PATH . '/core/acl.php';

✅ Подключаем сервис модуля:
require_once ROOT_PATH . '/modules/<code>/lib/service.php';

✅ Audit — только если файл реально есть:
$auditFile = ROOT_PATH . '/core/audit.php';
if (is_file($auditFile)) require_once $auditFile;

✅ Запрет: никаких “core/csrf.php” и других несуществующих include
5) Правило CSRF

CSRF у нас в /core/security.php:

генерим: csrf_token()

проверяем: csrf_check($_POST['_csrf'] ?? '')

Никаких csrf_guard() если его нет.

6) Правило ACL (ключевое, чтобы не повторять баги с ролями)

ACL проверяется:

на входе в модуль (скрытие меню по roles)

в handler’е на каждом action (обязательно)

Пример:

вход: acl_guard(['admin','manager'])

delete/set_role: acl_guard(['admin'])

7) Правило БД: роли не в users.role

У нас роли так:

roles

user_roles

users без role колонки

Значит:

любые запросы типа SELECT role FROM users — запрещены

роль получаем только через join или auth_user_role()

8) Правило транзакций (сегодняшний баг “already active transaction”)
❗Запрещено:

открывать транзакцию в users_create() и внутри вызывать users_set_role() которая тоже делает beginTransaction()

✅ Допустимые варианты:

users_set_role() без транзакции, а транзакция только в create

users_set_role() делает транзакцию только если её нет:

if (!$pdo->inTransaction()) $pdo->beginTransaction();


Общее правило: один слой управляет транзакцией.

9) Правило телефона (чтобы не повторять проблему логина)
Единый стандарт:

В БД users.phone хранится только цифры, без +, пробелов, скобок.

Перед insert/update: обязательно phone_normalize()

Перед login: обязательно phone_normalize()

Если хочешь хранить +7... — тогда и поиск должен быть по такому формату. Но сейчас у нас “цифры”.

10) Правило ошибок и логов

Ошибки не показываем пользователю (display_errors=0)

Всё летит в logs/error.log

Audit действий — в БД audit_log

Важно:

audit не должен ломать поток: try/catch внутри audit

response функции (json_out) должны существовать и быть едиными

11) “Правило моего поведения” (чтобы ты не ловил мои косяки)

Перед тем как я дам код:

я сверяю: какие функции/файлы уже существуют (из твоих вставок)

если хочу что-то новое — пишу:

точный путь

точное имя функции

и помечаю “Новый файл / Новая функция”

если ты не просил — я не добавляю

12) Мини-шаблон для новых модулей (как будем делать дальше)

Когда говоришь “делаем модуль X”, я выдаю:

modules/<x>/settings.php

modules/<x>/<x>.php (view)

modules/<x>/lib/service.php (функции x_*)

modules/<x>/assets/php/handler.php (action-роутинг)

минимальный js/css если нужно

И всё — без лишних файлов.