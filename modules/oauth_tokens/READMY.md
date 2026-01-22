# Модуль: oauth_tokens

## Назначение
Хранение OAuth client_id / client_secret и получение access_token.
- Admin: создаёт записи, редактирует, удаляет, назначает пользователя на запись.
- User: видит только свою запись и может запустить обновление токена (OAuth flow).

## Структура
/modules/oauth_tokens/
  oauth_tokens.php
  settings.php
  README.md
  /assets/php/handler.php

## Маршруты (adm shell)
/adm/index.php?m=oauth_tokens
/adm/index.php?m=oauth_tokens&a=create
/adm/index.php?m=oauth_tokens&a=update
/adm/index.php?m=oauth_tokens&a=delete
/adm/index.php?m=oauth_tokens&a=assign
/adm/index.php?m=oauth_tokens&a=start
/adm/index.php?m=oauth_tokens&a=callback

## Требование к OAuth Redirect URI (Яндекс)
Redirect URI должен указывать на callback:
  https://YOUR-DOMAIN/adm/index.php?m=oauth_tokens&a=callback


Важно: что поменять в Яндекс OAuth (чтобы “билось”)
В настройках приложения Яндекс OAuth ставишь:
Redirect URI:
https://ТВОЙ_ДОМЕН/adm/index.php?m=oauth_tokens&a=callback
Для яндекс приложения
https://testbot.fastycrm.ru/adm/index.php?m=oauth_tokens&a=callback
https://testbot.fastycrm.ru