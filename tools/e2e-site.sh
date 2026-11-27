#!/usr/bin/env bash
# End-to-end test of the tutor's signature (printed on letters) and About photo, uploaded in Settings.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-site.sh
# Removes any signature or photo already uploaded on that server.
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
upload() { local T; T=$(token "$1" "$BASE/studio/settings"); curl -s -o /dev/null -w '%{http_code}' -b "$1" -c "$1" --form-string "_token=$T" -F "picture=@$3" "$BASE/studio/settings/$2/upload"; }
post() { local T; T=$(token "$1" "$BASE/studio/settings"); curl -s -o /dev/null -w '%{http_code}' -b "$1" -c "$1" --data-urlencode "_token=$T" "$BASE$2"; }

sql "DELETE FROM login_attempts"
TJ="$WORK/t.txt"; login "$TJ" 0999123456 correct-horse-1
post "$TJ" /studio/settings/signature/remove >/dev/null; post "$TJ" /studio/settings/photo/remove >/dev/null
php -r '$i = imagecreatetruecolor(1200, 500); imagefill($i, 0, 0, imagecolorallocate($i, 248, 248, 245)); imagesetthickness($i, 6); imageline($i, 200, 300, 1000, 200, imagecolorallocate($i, 15, 15, 30)); imagejpeg($i, $argv[1], 90);' "$WORK/sig.jpg"
php -r '$i = imagecreatetruecolor(1500, 2000); imagefill($i, 0, 0, imagecolorallocate($i, 30, 80, 140)); imagejpeg($i, $argv[1], 90);' "$WORK/me.jpg"
printf 'not a picture' > "$WORK/fake.jpg"
CODE=$(sql "SELECT code FROM issued_documents ORDER BY id DESC LIMIT 1")

echo "Before uploading"
check "settings offers a signature"        "Upload your signature" "$(curl -s -b "$TJ" "$BASE/studio/settings")"
check "no signature yet (404)"             "404" "$(code "$BASE/site/signature")"
check "About shows initials"               'class="story__photo" aria-hidden="true"><span>BE' "$(curl -s "$BASE/about")"
if [[ -n "$CODE" ]]; then check "letter has a blank signing line" 'class="letter__signature"' "$(curl -s -b "$TJ" "$BASE/letters/$CODE")"; fi

echo "Uploading"
check "fake picture refused"               "303" "$(upload "$TJ" signature "$WORK/fake.jpg")"
check "  explained"                        "Use a JPG, PNG or WebP" "$(curl -s -b "$TJ" "$BASE/studio/settings")"
check "unknown kind (404)"                 "404" "$(upload "$TJ" logo "$WORK/sig.jpg")"
check "signature saved"                    "303" "$(upload "$TJ" signature "$WORK/sig.jpg")"
check "  served as PNG"                    "image/png" "$(curl -s -D - -o /dev/null "$BASE/site/signature")"
check "  background removed (has alpha)"   "RGBA" "$(curl -s "$BASE/site/signature" | php -r '$s = stream_get_contents(STDIN); echo ord($s[25]) === 6 ? "RGBA" : "no";')"
check "  kept outside the web root"        "uploads/site/signature-" "$(sql "SELECT value FROM settings WHERE name='tutor_signature'")"
check "photo saved"                        "303" "$(upload "$TJ" photo "$WORK/me.jpg")"
check "  resized to 900 px"                "900" "$(curl -s "$BASE/site/photo" | php -r 'echo getimagesizefromstring(stream_get_contents(STDIN))[0];')"
check "About shows the photo"              'src="/site/photo?v=' "$(curl -s "$BASE/about")"
if [[ -n "$CODE" ]]; then
    L=$(curl -s -b "$TJ" "$BASE/letters/$CODE")
    check "letter prints the signature"    'src="/site/signature?v=' "$L"
    check "  signed by name"               "Blessings Emulyn" "$L"
fi

echo "Only the admin"
SJ="$WORK/s.txt"
HASH=$(php -r 'echo password_hash("site-pass-1", PASSWORD_DEFAULT);')
sql "DELETE FROM users WHERE phone='+265997900001'"
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('alumni', 'Site Alum', '+265997900001', 'female', '$HASH')"
login "$SJ" 0997900001 site-pass-1
check "others cannot upload (403)"         "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" -X POST "$BASE/studio/settings/signature/upload")"
check "  or remove (403)"                  "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" -X POST "$BASE/studio/settings/signature/remove")"
sql "DELETE FROM users WHERE phone='+265997900001'"

echo "Removing"
OLD=$(sql "SELECT value FROM settings WHERE name='about_photo'")
post "$TJ" /studio/settings/photo/remove >/dev/null
check "photo removed (404)"                "404" "$(code "$BASE/site/photo")"
if [[ -n "${STORAGE_DIR:-}" ]]; then check "  file deleted" "gone" "$([[ -f "$STORAGE_DIR/$OLD" ]] && echo there || echo gone)"; fi
check "About back to initials"             '<span>BE</span>' "$(curl -s "$BASE/about")"

echo; echo "Site: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
