#!/bin/bash
# End-to-end walk through the public demo, the way a visitor's browser does it.
#
# Defaults to the demo running on this machine. Point it at a container with:
#   DEMO_URL=http://127.0.0.1:9092 DEMO_MARIADB="docker exec t-cb mariadb" ./test-demo.sh
U="${DEMO_URL:-http://127.0.0.1:8081}"
MARIADB="${DEMO_MARIADB:-mariadb}"
J=$(mktemp)
trap 'rm -f "$J"' EXIT

fail=0
check() {
	if [ "$2" = "$3" ]; then printf '  ok    %-38s %s\n' "$1" "$3"
	else printf '  FAIL  %-38s expected %s, got %s\n' "$1" "$2" "$3"; fail=1; fi
}

echo "the login page"
check "login page loads" 200 "$(curl -s -c "$J" -o /dev/null -w '%{http_code}' "$U/")"
check "username hint visible" yes \
	"$(curl -s "$U/" | grep -q '// admin' && echo yes || echo no)"
check "password hint visible" yes \
	"$(curl -s "$U/" | grep -q '// 1234' && echo yes || echo no)"

echo "session gets its own empty database"
TOKEN=$(curl -s -b "$J" -c "$J" "$U/get_csrf.php" | sed -E 's/.*"token":"([^"]+)".*/\1/')
check "csrf token issued" 64 "${#TOKEN}"

DBCOUNT_BEFORE=$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'certby\\_demo\\_%';")

LOGIN=$(curl -s -b "$J" -c "$J" -X POST "$U/auth.php" \
	-H 'Content-Type: application/json' -H "X-CSRF-Token: $TOKEN" \
	-d '{"action":"login","username":"admin","password":"1234"}')
check "login succeeds" true "$(echo "$LOGIN" | grep -o '"success":[a-z]*' | cut -d: -f2)"
check "role is operator" operator "$(echo "$LOGIN" | sed -E 's/.*"role":"([^"]+)".*/\1/')"

DBCOUNT_AFTER=$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'certby\\_demo\\_%';")
check "a demo database appeared" "$((DBCOUNT_BEFORE + 1))" "$DBCOUNT_AFTER"

DB=$($MARIADB -u root -N -B -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'certby\\_demo\\_%' ORDER BY schema_name DESC LIMIT 1;")
check "it has all 20 tables" 20 \
	"$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';")"
check "companies start empty" 0 "$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM \`$DB\`.companies;")"
check "certifications start empty" 0 "$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM \`$DB\`.certifications;")"
check "one admin user" 1 "$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM \`$DB\`.users;")"

echo "the app behind the login"
check "dashboard loads" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/dashboard.php")"
check "company management" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/company_management.php")"
check "certification page" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/certification.php")"
check "planning page" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/planning.php")"
check "reports page" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/reports.php")"
check "mail templates" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/mail_templates.php")"
check "demo inbox" 200 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$U/demo_inbox.php")"

echo "a second visitor is isolated"
J2=$(mktemp)
T2=$(curl -s -c "$J2" -b "$J2" "$U/get_csrf.php" | sed -E 's/.*"token":"([^"]+)".*/\1/')
curl -s -b "$J2" -c "$J2" -X POST "$U/auth.php" -H 'Content-Type: application/json' \
	-H "X-CSRF-Token: $T2" -d '{"action":"login","username":"admin","password":"1234"}' >/dev/null
DBCOUNT2=$($MARIADB -u root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'certby\\_demo\\_%';")
check "second visitor, second database" "$((DBCOUNT_BEFORE + 2))" "$DBCOUNT2"
rm -f "$J2"

echo
[ "$fail" = 0 ] && echo "the demo works end to end" || echo "FAILURES ABOVE"
exit "$fail"
