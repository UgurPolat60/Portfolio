#!/bin/bash
# Drops the per-visitor demo databases once they go cold.
#
# A visitor's database is theirs alone and lives only as long as they do. There
# is no "delete my data" button to trust and no shared state to leak: after
# TTL_MINUTES with nothing written to it, the whole database goes away.

TTL_MINUTES=${TTL_MINUTES:-90}
SLEEP_SECONDS=${SLEEP_SECONDS:-600}

sweep() {
	local stale
	stale=$(mariadb -u root -N -B -e "
		SELECT table_schema
		FROM information_schema.tables
		WHERE table_schema LIKE 'certby\\_demo\\_%'
		GROUP BY table_schema
		HAVING MAX(COALESCE(update_time, create_time)) < NOW() - INTERVAL $TTL_MINUTES MINUTE;")

	for db in $stale; do
		mariadb -u root -e "DROP DATABASE \`$db\`;" && echo "$(date '+%H:%M:%S') dropped $db"
	done
}

if [ "${1:-}" = "--once" ]; then
	sweep
	exit 0
fi

echo "janitor: dropping demo databases idle for more than $TTL_MINUTES minutes"
while true; do
	sweep
	sleep "$SLEEP_SECONDS"
done
