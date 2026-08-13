#!/bin/bash
# What start.sh does, minus the parts the platform already handles: no TLS, no
# port to choose, no second site. Just the database, the mail sink, the janitor
# and the app — and the app runs in the foreground so the container lives and
# dies with it.

set -u
cd /app || exit 1
mkdir -p mail logs

# A container filesystem starts empty every deploy, so the data directory may
# not exist yet. The demo databases are meant to be throwaway anyway.
if [ ! -d /var/lib/mysql/mysql ]; then
	echo "initialising the data directory…"
	mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

echo "database…"
mariadbd-safe --user=mysql >logs/mariadb.log 2>&1 &
for _ in $(seq 60); do
	mariadb -u root -e "SELECT 1" >/dev/null 2>&1 && break
	sleep 1
done
if ! mariadb -u root -e "SELECT 1" >/dev/null 2>&1; then
	echo "FAILED: mariadb is not up"
	tail -20 logs/mariadb.log
	exit 1
fi
echo "  mariadb ready"

echo "mail sink…"
python3 mailsink.py >logs/mailsink.log 2>&1 &

# a shorter TTL than the laptop version: 512 MB does not hold 40 databases
echo "janitor…"
TTL_MINUTES="${TTL_MINUTES:-30}" bash janitor.sh >logs/janitor.log 2>&1 &

echo "certby on ${PORT:-8081}…"
cd certby || exit 1
# workers, because the app talks to itself over AJAX and a single-process
# built-in server would deadlock waiting on its own request
export PHP_CLI_SERVER_WORKERS=6
exec php -S "0.0.0.0:${PORT:-8081}" router.php
