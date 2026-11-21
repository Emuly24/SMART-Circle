#!/usr/bin/env bash
# End-to-end test of the foundation against a real MariaDB/MySQL database.
#
# Usage (built-in PHP server, started for you):
#   DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=sc_e2e DB_USER=... DB_PASS=... tools/e2e.sh
# Usage (an already running server, e.g. Apache with the real .htaccess files):
#   BASE_URL=http://127.0.0.1:8097 STORAGE_DIR=/var/www/sc/storage INSTALL_TOKEN=... tools/e2e.sh
# The database must exist and be EMPTY (the test installs into it).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8098}"
WORK="$(mktemp -d)"
JAR="$WORK/cookies.txt"
PASS=0; FAIL=0
if [[ -n "${BASE_URL:-}" ]]; then
    BASE="$BASE_URL"; STORAGE="${STORAGE_DIR:?}"; TOKEN="${INSTALL_TOKEN:?}"
else
    BASE="http://127.0.0.1:$PORT"; STORAGE="$WORK/storage"; mkdir -p "$STORAGE"
    TOKEN="e2e-install-token-0123456789"
fi

if [[ -z "${BASE_URL:-}" ]]; then
cat > "$WORK/secrets.php" <<PHP
<?php return [
  'app' => ['debug' => true],
  'db' => ['host' => '${DB_HOST:-127.0.0.1}', 'port' => ${DB_PORT:-3306}, 'name' => '${DB_NAME:?}', 'user' => '${DB_USER:?}', 'password' => '${DB_PASS:-}'],
  'security' => ['install_token' => '$TOKEN', 'login_max_attempts' => 3],
  'paths' => ['storage' => '$STORAGE'],
];
PHP

SMARTCIRCLE_SECRETS="$WORK/secrets.php" php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/tools/dev-router.php" >"$WORK/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; rm -rf "$WORK"' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "$BASE/" && break; sleep 0.1; done
else
touch "$WORK/server.log"; trap 'rm -rf "$WORK"' EXIT
fi

check() { # name, expected, actual
    if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '$3')"; fi
}
status() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$JAR" -c "$JAR" "$@"; }
body() { curl -s -b "$JAR" -c "$JAR" "$@"; }
token() { body "$1" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "Install"
check "home redirects to installer"      "303 $BASE/install" "$(status "$BASE/")"
T=$(token "$BASE/install")
check "install form has a CSRF token"    "64" "${#T}"
check "POST without CSRF token is refused" "403" "$(status -d 'full_name=x' "$BASE/install")"
check "wrong install token is rejected"  "422" "$(status -d "_token=$T&install_token=nope&full_name=Tutor%20One&phone=0999123456&password=correct-horse-1&password_confirm=correct-horse-1" "$BASE/install")"
check "short password is rejected"       "Use at least 10" "$(body -d "_token=$T&install_token=$TOKEN&full_name=Tutor%20One&phone=0999123456&password=short&password_confirm=short" "$BASE/install")"
check "valid install succeeds"           "303 $BASE/login?installed=1" "$(status -d "_token=$T&install_token=$TOKEN&full_name=Tutor%20One&phone=0999%20123%20456&password=correct-horse-1&password_confirm=correct-horse-1" "$BASE/install")"
check "installer is locked afterwards"   "404" "$(status "$BASE/install")"
check "lock file written"                "yes" "$([[ -f "$STORAGE/installed.lock" ]] && echo yes || echo no)"

echo "Pages and security headers"
check "home page renders"                "Understand it." "$(body "$BASE/")"
check "about page renders"               "Student Mentorship" "$(body "$BASE/about")"
check "unknown page is 404"              "404" "$(status "$BASE/does-not-exist")"
for private in src/Presentation/App.php config/app.php secrets.php secrets.example.php database/migrations/0001_core.sql \
               storage/installed.lock .htaccess composer.json CLAUDE.md public/../secrets.php; do
    code=$(curl -s -o "$WORK/leak" -w '%{http_code}' "$BASE/$private")
    if grep -qE "<\?php|CREATE TABLE|install_token|password" "$WORK/leak" && [[ "$code" == "200" ]]; then
        check "private file $private is not served" "not served" "LEAKED ($code)"
    else
        check "private file $private is not served" "ok" "ok ($code)"
    fi
done
HDRS=$(curl -s -D - -o /dev/null "$BASE/")
check "CSP header"                       "Content-Security-Policy: default-src 'self'" "$HDRS"
check "frame protection header"          "X-Frame-Options: DENY" "$HDRS"
check "session cookie is HttpOnly"       "HttpOnly" "$HDRS"
check "stylesheet served"                "200" "$(status "$BASE/assets/css/app.css")"
check "logo served"                      "200" "$(status "$BASE/assets/brand/logo-horizontal.svg")"
check "font served"                      "200" "$(status "$BASE/assets/fonts/inter-regular.woff2")"

echo "Login and logout"
rm -f "$JAR"
check "dashboard needs login"            "303 $BASE/login" "$(status "$BASE/dashboard")"
T=$(token "$BASE/login")
check "wrong password is rejected"       "incorrect" "$(body -d "_token=$T&phone=0999123456&password=wrong-password" "$BASE/login")"
T=$(token "$BASE/login")
check "correct login goes to dashboard"  "303 $BASE/dashboard" "$(status -d "_token=$T&phone=+265%20999%20123%20456&password=correct-horse-1" "$BASE/login")"
check "tutor dashboard shows"            "Welcome, Tutor" "$(body "$BASE/dashboard")"
check "login page redirects when logged in" "303 $BASE/dashboard" "$(status "$BASE/login")"
check "logout without CSRF is refused"  "403" "$(status -X POST "$BASE/logout")"
T=$(token "$BASE/dashboard")
check "logout works"                     "303 $BASE/" "$(status -d "_token=$T" "$BASE/logout")"
check "dashboard needs login again"      "303 $BASE/login" "$(status "$BASE/dashboard")"

echo "Brute-force lockout (3 attempts in this test)"
rm -f "$JAR"
for i in 1 2 3; do T=$(token "$BASE/login"); body -d "_token=$T&phone=0888000111&password=guess$i" "$BASE/login" >/dev/null; done
T=$(token "$BASE/login")
check "4th attempt is locked out"        "Too many attempts" "$(body -d "_token=$T&phone=0888000111&password=guess4" "$BASE/login")"

echo
echo "Passed: $PASS  Failed: $FAIL"
if grep -qiE "fatal|warning|deprecated" "$WORK/server.log"; then
    echo "PHP problems in server log:"; grep -iE "fatal|warning|deprecated" "$WORK/server.log" | head -10; FAIL=$((FAIL+1))
fi
exit $(( FAIL > 0 ))
