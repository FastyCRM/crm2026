<?php
/**
 * FILE: /modules/users/users.php
 * ROLE: view модуля Users (таблица + формы)
 * CONNECTIONS:
 *  - core/auth + core/security + core/response + (core/users*) функции
 */
declare(strict_types=1);

$uid = auth_user_id();
if (!$uid) {
    http_response_code(401);
    exit('Unauthorized');
}

$role = auth_user_role(); // admin|manager|user

// ACL на просмотр (user сюда не должен попасть даже из меню, но на всякий случай)
acl_guard(['admin', 'manager']);

$csrf = csrf_token();

/**
 * Данные: берем через core-функцию (предпочтительно)
 * Если у тебя уже есть users_list(), используй её.
 * Ниже предполагаю наличие users_list_all().
 */
$rows = users_list_all(); // array<int,array<string,mixed>>
?>
<div class="page-head">
  <div class="row">
    <div class="title">Пользователи</div>
    <div class="right"></div>

    <?php if ($role === 'admin' || $role === 'manager'): ?>
      <button class="btn primary" type="button" onclick="UsersUI.openCreate()">Добавить</button>
    <?php endif; ?>
  </div>
  <div class="muted" style="margin-top:6px;">
    Admin: всё + роли + удаление. Manager: добавление/редактирование без ролей и удаления.
  </div>
</div>

<div class="card card--soft">
  <div class="card__body">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Имя</th>
          <th>Email</th>
          <th>Телефон</th>
          <th>Роль</th>
          <th style="width:220px;">Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string)($u['phone'] ?? ''), ENT_QUOTES) ?></td>
          <td>
            <?php if ($role === 'admin'): ?>
              <select class="select" onchange="UsersUI.setRole(<?= (int)$u['id'] ?>, this.value)">
                <?php
                  $r = (string)($u['role'] ?? 'user');
                  foreach (['admin'=>'admin','manager'=>'manager','user'=>'user'] as $rv):
                ?>
                  <option value="<?= $rv ?>" <?= $r===$rv?'selected':'' ?>><?= $rv ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <span class="badge"><?= htmlspecialchars((string)($u['role'] ?? 'user'), ENT_QUOTES) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="row" style="gap:10px;">
              <?php if ($role === 'admin' || $role === 'manager'): ?>
                <button class="btn sm" type="button"
                        onclick='UsersUI.openEdit(<?= (int)$u["id"] ?>, <?= json_encode($u, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>
                  Редактировать
                </button>
              <?php endif; ?>

              <?php if ($role === 'admin'): ?>
                <button class="btn sm danger" type="button" onclick="UsersUI.remove(<?= (int)$u['id'] ?>)">
                  Удалить
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Модалка: Create/Edit (простая, без внешних либ) -->
<div id="users-modal" class="users-modal" style="display:none;">
  <div class="users-modal__backdrop" onclick="UsersUI.close()"></div>
  <div class="users-modal__panel card">
    <div class="card__head">
      <div class="title" id="users-modal-title">Пользователь</div>
      <div class="right"></div>
      <button class="btn sm ghost" type="button" onclick="UsersUI.close()">Закрыть</button>
    </div>
    <div class="card__body">
      <form id="users-form" onsubmit="return UsersUI.submit(event);">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="id" value="">

        <div class="grid grid--2">
          <div>
            <label>Имя</label>
            <input name="name" class="input" required>
          </div>
          <div>
            <label>Email</label>
            <input name="email" class="input" type="email" required>
          </div>
          <div>
            <label>Телефон</label>
            <input name="phone" class="input">
          </div>
          <div>
            <label>Пароль (только при создании / смене)</label>
            <input name="password" class="input" type="password" placeholder="оставь пустым чтобы не менять">
          </div>
        </div>

        <div style="margin-top:14px;" class="row">
          <button class="btn primary" type="submit">Сохранить</button>
          <button class="btn ghost" type="button" onclick="UsersUI.close()">Отмена</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const UsersUI = {
  mode: 'create',
  openCreate(){
    this.mode = 'create';
    const m = document.getElementById('users-modal');
    const f = document.getElementById('users-form');
    document.getElementById('users-modal-title').textContent = 'Добавить пользователя';
    f.id.value = '';
    f.name.value = '';
    f.email.value = '';
    f.phone.value = '';
    f.password.value = '';
    m.style.display = 'block';
  },
  openEdit(id, data){
    this.mode = 'edit';
    const m = document.getElementById('users-modal');
    const f = document.getElementById('users-form');
    document.getElementById('users-modal-title').textContent = 'Редактировать пользователя';
    f.id.value = id;
    f.name.value = data.name || '';
    f.email.value = data.email || '';
    f.phone.value = data.phone || '';
    f.password.value = '';
    m.style.display = 'block';
  },
  close(){
    document.getElementById('users-modal').style.display = 'none';
  },
  async submit(e){
    e.preventDefault();
    const f = document.getElementById('users-form');
    const formData = new FormData(f);

    const action = (this.mode === 'create') ? 'create' : 'update';
    const res = await fetch(`/adm/index.php?m=users&a=${action}`, { method:'POST', body: formData });
    if (!res.ok) { alert('Ошибка сохранения'); return false; }
    const j = await res.json().catch(()=>null);
    if (!j || j.ok !== true) { alert(j?.error || 'Ошибка'); return false; }
    location.reload();
    return false;
  },
  async remove(id){
    if (!confirm('Удалить пользователя?')) return;
    const fd = new FormData();
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fd.append('id', id);
    const res = await fetch('/adm/index.php?m=users&a=delete', { method:'POST', body: fd });
    const j = await res.json().catch(()=>null);
    if (!res.ok || !j || j.ok !== true) { alert(j?.error || 'Ошибка удаления'); return; }
    location.reload();
  },
  async setRole(id, role){
    const fd = new FormData();
    fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    fd.append('id', id);
    fd.append('role', role);
    const res = await fetch('/adm/index.php?m=users&a=set_role', { method:'POST', body: fd });
    const j = await res.json().catch(()=>null);
    if (!res.ok || !j || j.ok !== true) { alert(j?.error || 'Ошибка смены роли'); location.reload(); }
  }
};
</script>
