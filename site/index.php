<?php
/**
 * FILE: /index.php
 * ROLE: Public index for Yandex OAuth "Suggest Hostname" verification.
 * CONNECTIONS: none (no DB, no auth, no CRM bootstrap)
 *
 * PURPOSE:
 *  - Yandex checks Suggest Hostname by loading this index and seeing YaAuthSuggest SDK usage.
 *  - This page is NOT your CRM admin. It is only a public landing/verification page.
 */

declare(strict_types=1);

// === CONFIG (edit these two lines) ===
$CLIENT_ID    = '50c550041b65474bb8815a6093af3137';
$REDIRECT_URI = 'https://gen2.fastycrm.ru/adm/index.php?m=oauth_tokens&a=callback';

// (optional) if you want to force exact origin for init() 2nd param:
$ORIGIN = 'https://gen2.fastycrm.ru';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>gen2.fastycrm.ru</title>

  <!-- Yandex Passport SDK -->
  <script src="https://yastatic.net/s3/passport-sdk/autofill/v1/sdk-suggest-with-polyfills-latest.js"></script>

  <!-- Minimal styles (inline CSS is OK for HTML; JS logic is below and small) -->
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e8eefc;}
    .wrap{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
    .card{width:min(720px, 100%); background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:14px; padding:22px;}
    .row{display:flex; gap:14px; flex-wrap:wrap; align-items:center; justify-content:space-between;}
    .muted{opacity:.75; font-size:14px; line-height:1.4;}
    .btns{display:flex; gap:10px; align-items:center; flex-wrap:wrap;}
    a{color:#9cc2ff; text-decoration:none;}
    a:hover{text-decoration:underline;}
    code{background:rgba(255,255,255,.08); padding:2px 6px; border-radius:6px;}
    .block{margin-top:14px;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="row">
        <div>
          <div style="font-size:18px;font-weight:700;">Проверка Яндекс OAuth (Suggest Hostname)</div>
          <div class="muted block">
            Эта страница нужна только чтобы Яндекс видел установленный SDK и кнопку авторизации.
            CRM админка находится в <a href="/adm/">/adm/</a>.
          </div>
        </div>
        <div class="btns">
          <a class="muted" href="/adm/">Перейти в админку</a>
        </div>
      </div>

      <div class="block muted">
        client_id: <code><?= htmlspecialchars($CLIENT_ID, ENT_QUOTES) ?></code><br>
        redirect_uri: <code><?= htmlspecialchars($REDIRECT_URI, ENT_QUOTES) ?></code>
      </div>

      <div class="block" id="buttonContainerId"></div>

      <div class="block muted" id="hint"></div>
    </div>
  </div>

  <script>
  (function(){
    // CONFIG from PHP
    var clientId = <?= json_encode($CLIENT_ID, JSON_UNESCAPED_SLASHES) ?>;
    var redirectUri = <?= json_encode($REDIRECT_URI, JSON_UNESCAPED_SLASHES) ?>;
    var origin = <?= json_encode($ORIGIN, JSON_UNESCAPED_SLASHES) ?>;

    var hint = document.getElementById('hint');

    function say(msg){
      if (hint) hint.textContent = msg;
      console.log(msg);
    }

    if (!window.YaAuthSuggest || !window.YaAuthSuggest.init) {
      say('SDK Яндекса не загрузился (YaAuthSuggest.init не найден).');
      return;
    }

    // Init button
    window.YaAuthSuggest.init(
      {
        client_id: clientId,
        response_type: 'code',
        redirect_uri: redirectUri
      },
      origin,
      {
        view: "button",
        parentId: "buttonContainerId",
        buttonSize: 'm',
        buttonView: 'main',
        buttonTheme: 'light',
        buttonBorderRadius: "0",
        buttonIcon: 'ya'
      }
    )
    .then(function(res){ return res.handler(); })
    .then(function(data){
      // Usually you'll see data with 'code' or token-like payload depending on SDK mode.
      say('Авторизация прошла, смотри console.log.');
      console.log('YaAuthSuggest data:', data);
    })
    .catch(function(err){
      say('Ошибка YaAuthSuggest: смотри console.log.');
      console.log('YaAuthSuggest error:', err);
    });
  })();
  </script>
</body>
</html>
