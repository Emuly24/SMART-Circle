#!/usr/bin/env bash
# End-to-end test of a code update arriving before its database update (what happens on the live
# site between uploading new code and pressing "Update database"). It undoes the newest migration
# (0014) on the TEST database, checks the site stays usable and safe, then applies it again with
# the admin's button. Run it against a throwaway test database only.
#
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-update.sh
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }

sql "DELETE FROM login_attempts"
TJ="$WORK/t.txt"; login "$TJ" 0999123456 correct-horse-1

echo "Undo the newest migration (as if new code was uploaded first)"
$MYSQL -e "ALTER TABLE users DROP COLUMN avatar_at;
  ALTER TABLE applications DROP COLUMN guardian_whatsapp, DROP COLUMN guardian_email, DROP COLUMN guardian_language;
  ALTER TABLE student_profiles DROP INDEX uq_student_profiles_guardian_token, DROP COLUMN guardian_relationship, DROP COLUMN guardian_whatsapp,
    DROP COLUMN guardian_email, DROP COLUMN guardian_language, DROP COLUMN guardian_token, DROP COLUMN guardian_confirmed_via, DROP COLUMN guardian_declined_at;
  DELETE FROM schema_migrations WHERE version = '0014_guardians_profiles';"

echo "The site stays usable"
check "signed-in admin gets the update page" "One step to finish the update" "$(curl -s -b "$TJ" "$BASE/")"
check "  on any page"                      "Update the database now" "$(curl -s -b "$TJ" "$BASE/studio/students")"
check "  lists what will be applied"       "0014_guardians_profiles" "$(curl -s -b "$TJ" "$BASE/dashboard")"
check "visitors see a calm notice"         "SMART Circle is being updated" "$(curl -s "$BASE/")"
check "  with 503 and Retry-After"         "503" "$(code "$BASE/apply")"
check "  and a way for the admin to log in" 'href="/login"' "$(curl -s "$BASE/about")"
check "the login page still works"         "200" "$(code "$BASE/login")"
GJ="$WORK/g.txt"; login "$GJ" 0999123456 correct-horse-1
check "  signing in works"                 "One step to finish the update" "$(curl -s -b "$GJ" "$BASE/dashboard")"
check "no error was logged as a crash"     "0" "$(grep -c "Unknown column 'avatar_at'" "${STORAGE_DIR:-/dev/null}/logs/app-$(date +%F).log" 2>/dev/null || echo 0)"

echo "The admin applies the update"
T=$(token "$TJ" "$BASE/dashboard")
check "press the button"                   "303" "$(curl -s -o /dev/null -w '%{http_code}' -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" "$BASE/dashboard/database-update")"
check "  migration recorded"               "1" "$(sql "SELECT COUNT(*) FROM schema_migrations WHERE version = '0014_guardians_profiles'")"
check "  column back"                      "avatar_at" "$(sql "SHOW COLUMNS FROM users LIKE 'avatar_at'")"
check "dashboard works again"              "200" "$(code -b "$TJ" "$BASE/dashboard")"
check "home page for visitors again"       "Apply" "$(curl -s "$BASE/")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
