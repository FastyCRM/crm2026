/**
 * FILE: /modules/oauth_tokens/assets/js/oauth_tokens.js
 * ROLE: UI-логика модалки + CRUD через fetch + OAuth start через popup window
 * CONNECTIONS:
 *  - Разметка из oauth_tokens.php (id: oauth-modal, oauth-form и т.д.)
 */

const OauthTokensUI = {
  mode: 'create',

  _modal() { return document.getElementById('oauth-modal'); },
  _form() { return document.getElementById('oauth-form'); },
  _title() { return document.getElementById('oauth-modal-title'); },

  openCreate(){
    this.mode = 'create';
    const m = this._modal();
    const f = this._form();
    this._title().textContent = 'Добавить токен';

    f.id.value = '';
    f.name.value = '';
    f.client_id.value = '';
    f.client_secret.value = '';
    if (f.assign_user_id) f.assign_user_id.value = '0';

    m.style.display = 'block';
  },

  openEdit(data){
    this.mode = 'edit';
    const m = this._modal();
    const f = this._form();
    this._title().textContent = 'Редактировать токен';

    f.id.value = data.id || '';
    f.name.value = data.name || '';
    f.client_id.value = data.client_id || '';
    f.client_secret.value = data.client_secret || '';
    if (f.assign_user_id) f.assign_user_id.value = String(data.assign_user_id || 0);

    m.style.display = 'block';
  },

  close(){
    const m = this._modal();
    if (m) m.style.display = 'none';
  },

  async submit(e){
    e.preventDefault();
    const f = this._form();
    const fd = new FormData(f);

    const action = (this.mode === 'create') ? 'create' : 'update';
    const res = await fetch(`/adm/index.php?m=oauth_tokens&a=${action}`, { method:'POST', body: fd });
    const j = await res.json().catch(()=>null);

    if (!res.ok || !j || j.ok !== true) {
      alert(j?.error || 'Ошибка');
      return false;
    }
    location.reload();
    return false;
  },

  async remove(id){
    if (!confirm('Удалить токен?')) return;
    const csrf = document.querySelector('input[name="_csrf"]')?.value || '';
    if (!csrf) { alert('CSRF не найден'); return; }

    const fd = new FormData();
    fd.append('_csrf', csrf);
    fd.append('id', String(id));

    const res = await fetch('/adm/index.php?m=oauth_tokens&a=delete', { method:'POST', body: fd });
    const j = await res.json().catch(()=>null);

    if (!res.ok || !j || j.ok !== true) {
      alert(j?.error || 'Ошибка удаления');
      return;
    }
    location.reload();
  },

  /**
   * OAuth flow MUST be in separate window (popup).
   * We open popup first (to avoid browser blocking),
   * then POST form into that window.
   */
  start(id){
    const csrf = document.querySelector('input[name="_csrf"]')?.value || '';
    if (!csrf) { alert('CSRF не найден'); return; }

    const w = window.open(
      '',
      'yandex_oauth',
      'width=520,height=720,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes'
    );
    if (!w) { alert('Popup заблокирован браузером'); return; }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/adm/index.php?m=oauth_tokens&a=start';
    form.target = 'yandex_oauth';

    const i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = '_csrf'; i1.value = csrf;
    form.appendChild(i1);

    const i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'id'; i2.value = String(id);
    form.appendChild(i2);

    const i3 = document.createElement('input');
    i3.type = 'hidden'; i3.name = 'popup'; i3.value = '1';
    form.appendChild(i3);

    document.body.appendChild(form);
    form.submit();
    form.remove();
  }
};

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') OauthTokensUI.close();
});
