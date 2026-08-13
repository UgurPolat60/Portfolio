#!/bin/bash
# Brings up everything behind the portfolio:
#
#   :8080  webserv          the C server and the static site
#   :8081  certby demo      the PHP platform, one database per visitor
#   :8000  django portfolio the other site
#   :1025  mail sink        catches what the platform sends
#
# Run it as root inside WSL. Safe to run twice: it stops what it started.

set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
WEBSERV="$HERE/../webserv"
DJANGO="/mnt/c/Users/Ugur/Desktop/CV/Websitem"
LOGS="$HERE/logs"
mkdir -p "$LOGS" "$HERE/mail"

stop() { pkill -f "$1" >/dev/null 2>&1; }

echo "stopping anything already running…"
stop "mailsink.py"
stop "php -S 0.0.0.0:8081"
stop "manage.py runserver"
stop "janitor.sh"
stop "webserv 8080"
sleep 1

echo "database…"
service mariadb start >/dev/null 2>&1
for _ in $(seq 30); do
	mariadb -u root -e "SELECT 1" >/dev/null 2>&1 && break
	sleep 1
done
mariadb -u root -e "SELECT 1" >/dev/null 2>&1 || { echo "  FAILED: mariadb is not up"; exit 1; }
echo "  mariadb ready"

echo "mail sink…"
nohup /opt/djvenv/bin/python "$HERE/mailsink.py" >"$LOGS/mailsink.log" 2>&1 &
sleep 1

echo "certby demo…"
# workers, because the app talks to itself over AJAX and a single-process
# built-in server would deadlock waiting on its own request
cd "$HERE/certby" || exit 1
PHP_CLI_SERVER_WORKERS=6 nohup php -S 0.0.0.0:8081 router.php >"$LOGS/certby.log" 2>&1 &

echo "django portfolio…"
cd "$DJANGO" || exit 1
nohup /opt/djvenv/bin/python manage.py runserver 0.0.0.0:8000 --noreload >"$LOGS/django.log" 2>&1 &

echo "janitor…"
nohup bash "$HERE/janitor.sh" >"$LOGS/janitor.log" 2>&1 &

echo "webserv…"
cd "$WEBSERV" || exit 1
nohup ./webserv 8080 www >"$LOGS/webserv.log" 2>&1 &

sleep 3
echo
for probe in "8080 /" "8081 /" "8000 /"; do
	set -- $probe
	code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "http://127.0.0.1:$1$2")
	printf '  :%-5s %s\n' "$1" "$code"
done
echo
echo "site   http://localhost:8080"
echo "certby http://localhost:8081   (admin / 1234)"
echo "django http://localhost:8000"
