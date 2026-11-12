#!/usr/bin/env sh
# CRM Cabinet : démo hors ligne (données fictives, aucun compte externe). PHP 8.1+ dans le PATH, ou PHP=/chemin/vers/php.
# Identifiant : demo / mot de passe : demo. Supprimez data-demo/ pour repartir de zéro.
cd "$(dirname "$0")" || exit 1
PHP="${PHP:-php}"
PORT="${PORT:-8080}"
if ! "$PHP" -v >/dev/null 2>&1; then
  echo "PHP introuvable : installez PHP 8.1+ (extensions dom, mbstring, openssl, zip) ou lancez PHP=/chemin/vers/php ./demo.sh" >&2
  exit 1
fi
echo "CRM Cabinet - démo : http://127.0.0.1:$PORT  (identifiant demo / mot de passe demo)"
( sleep 1; (xdg-open "http://127.0.0.1:$PORT" || open "http://127.0.0.1:$PORT") >/dev/null 2>&1 ) &
CRM_DEMO=1 exec "$PHP" -S "127.0.0.1:$PORT" -t public public/routeur.php
