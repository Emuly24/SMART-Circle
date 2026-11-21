#!/usr/bin/env bash
# End-to-end test of lesson upload, database updates, publishing and access rules.
#
# Needs a running server that is ALREADY INSTALLED with tutor 0999123456 / correct-horse-1
# (e.g. right after tools/e2e.sh's install), plus a mysql client command for test setup:
#   BASE_URL=http://127.0.0.1:8098 STORAGE_DIR=... MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-lessons.sh
set -euo pipefail

BASE="${BASE_URL:?}"; STORAGE="${STORAGE_DIR:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
TJ="$WORK/tutor.txt"; SJ="$WORK/student.txt"
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '$3')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="(_token|csrf-token)" (value|content)="[^"]+"' | head -1 | sed -E 's/.*(value|content)="//;s/"//'; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
api() { curl -s -w '\n%{http_code}' -b "$TJ" -c "$TJ" -H "X-CSRF-Token: $CSRF" "$@"; }
field() { php -r '$d = json_decode(stream_get_contents(STDIN), true); echo is_array($d) && array_key_exists($argv[1], $d) ? (is_bool($d[$argv[1]]) ? ($d[$argv[1]] ? "true" : "false") : $d[$argv[1]]) : "";' "$1"; }

# A 2.6 MB lesson file: header + pseudo-audio, so it needs 3 pieces of 1 MB.
php -r '
$h = json_encode(["version"=>1,"page"=>["width"=>1600,"height"=>1000],"title"=>"Ohms law","durationMs"=>65000,"audioMime"=>"audio/mp4",
  "events"=>[["t"=>0,"type"=>"stroke","id"=>"s1","tool"=>"pen","color"=>"#14213D","width"=>7,"points"=>[[10,10,0.5,0],[200,200,0.8,500]]]]]);
$audio = ""; mt_srand(7); for ($i = 0; $i < 2600000; $i++) { $audio .= chr(mt_rand(0, 255)); }
file_put_contents($argv[1], "SCL1" . pack("V", strlen($h)) . $h . $audio);' "$WORK/lesson.scl"
SIZE=$(stat -c %s "$WORK/lesson.scl"); SHA=$(sha256sum "$WORK/lesson.scl" | cut -d' ' -f1)
dd if="$WORK/lesson.scl" of="$WORK/p1" bs=1048576 count=1 status=none
dd if="$WORK/lesson.scl" of="$WORK/p2" bs=1048576 skip=1 count=1 status=none
dd if="$WORK/lesson.scl" of="$WORK/p3" bs=1048576 skip=2 status=none

# Earlier test runs may have triggered the login lockout from this address.
$MYSQL -e "DELETE FROM login_attempts"

echo "Database update button"
# Simulate a pending update by un-applying the seed migration (no applications exist yet).
$MYSQL -e "DELETE FROM entry_quiz_questions; DELETE FROM intakes; DELETE FROM schema_migrations WHERE version='0004_seed';"
login "$TJ" 0999123456 correct-horse-1
check "dashboard offers the update"      "A database update is ready" "$(curl -s -b "$TJ" -c "$TJ" "$BASE/dashboard")"
T=$(token "$TJ" "$BASE/dashboard")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" "$BASE/dashboard/database-update"
check "update applied"                   "Database updated (1 update)" "$(curl -s -b "$TJ" -c "$TJ" "$BASE/dashboard")"
check "seed data restored by the update" "40" "$($MYSQL -N -e "SELECT COUNT(*) FROM entry_quiz_questions")"

echo "Upload API"
CSRF=$(token "$TJ" "$BASE/studio/lab")
check "studio page exposes a CSRF token" "64" "${#CSRF}"
check "upload without CSRF is refused"   "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$TJ" -H 'Content-Type: application/json' -d '{}' "$BASE/studio/uploads")"
R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"lesson-1\",\"title\":\"Ohms law\",\"size\":999999999,\"sha256\":\"$SHA\"}" "$BASE/studio/uploads")
check "too-large lesson is refused"      "413" "$(tail -1 <<<"$R")"
R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"lesson-1\",\"title\":\"Ohms law\",\"size\":$SIZE,\"sha256\":\"$SHA\"}" "$BASE/studio/uploads")
UP=$(head -1 <<<"$R" | field upload_id)
check "upload starts"                    "200" "$(tail -1 <<<"$R")"
R=$(api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p2" "$BASE/studio/uploads/$UP/chunk?offset=1048576")
check "out-of-order piece -> 409"        "409" "$(tail -1 <<<"$R")"
check "  ...and tells where to resume"   "0" "$(head -1 <<<"$R" | field received_bytes)"
R=$(api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p1" "$BASE/studio/uploads/$UP/chunk?offset=0")
check "piece 1 accepted"                 "1048576" "$(head -1 <<<"$R" | field received_bytes)"
R=$(api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p1" "$BASE/studio/uploads/$UP/chunk?offset=0")
check "repeated piece 1 is harmless"     "1048576" "$(head -1 <<<"$R" | field received_bytes)"
R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"lesson-1\",\"title\":\"Ohms law\",\"size\":$SIZE,\"sha256\":\"$SHA\"}" "$BASE/studio/uploads")
check "restart resumes the same upload"  "1048576" "$(head -1 <<<"$R" | field received_bytes)"
R=$(api -X POST "$BASE/studio/uploads/$UP/finish")
check "finishing early is refused"       "409" "$(tail -1 <<<"$R")"
api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p2" "$BASE/studio/uploads/$UP/chunk?offset=1048576" >/dev/null
api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p3" "$BASE/studio/uploads/$UP/chunk?offset=2097152" >/dev/null
R=$(api -X POST "$BASE/studio/uploads/$UP/finish")
LESSON=$(head -1 <<<"$R" | field lesson_id)
check "finish creates a lesson"          "200" "$(tail -1 <<<"$R")"
check "finishing twice gives same lesson" "$LESSON" "$(api -X POST "$BASE/studio/uploads/$UP/finish" | head -1 | field lesson_id)"
check "lesson stored on disk"            "yes" "$([[ -f "$STORAGE/lessons/$LESSON/part-000.bin" ]] && echo yes || echo no)"
check "upload folder cleaned up"         "yes" "$([[ ! -d "$STORAGE/uploads/lessons/$UP" ]] && echo yes || echo no)"
check "duration read from the file"      "65000" "$($MYSQL -N -e "SELECT duration_ms FROM lessons WHERE id=$LESSON")"

R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"bad-1\",\"title\":\"Bad\",\"size\":$SIZE,\"sha256\":\"$(printf 'a%.0s' {1..64})\"}" "$BASE/studio/uploads")
BAD=$(head -1 <<<"$R" | field upload_id)
for i in 1 2 3; do o=$(( (i-1) * 1048576 )); api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/p$i" "$BASE/studio/uploads/$BAD/chunk?offset=$o" >/dev/null; done
check "damaged file (checksum) refused"  "damaged" "$(api -X POST "$BASE/studio/uploads/$BAD/finish" | head -1)"
printf 'NOTALESSON' > "$WORK/junk"
R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"junk\",\"title\":\"Junk\",\"size\":10,\"sha256\":\"$(sha256sum "$WORK/junk" | cut -d' ' -f1)\"}" "$BASE/studio/uploads")
J=$(head -1 <<<"$R" | field upload_id)
check "non-lesson file refused"          "422" "$(api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/junk" "$BASE/studio/uploads/$J/chunk?offset=0" | tail -1)"

echo "Publishing rules"
T=$(token "$TJ" "$BASE/studio/lessons/$LESSON")
R=$(curl -s -w '\n%{http_code}' -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "title=Ohm's law & circuits" --data-urlencode "form=3" --data-urlencode "subject=physics" --data-urlencode "action=publish" "$BASE/studio/lessons/$LESSON")
check "publish without real-life sections refused" "Not published yet" "$(head -n -1 <<<"$R")"
check "  ...and lists what's missing"     "Why it matters" "$(head -n -1 <<<"$R")"
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "title=Ohm's law & circuits" --data-urlencode "form=3" --data-urlencode "subject=physics" \
  --data-urlencode "why_it_matters=Choosing safe fuses" --data-urlencode "where_in_malawi=ESCOM lines <script>alert(1)</script>" --data-urlencode "careers=Electrician" --data-urlencode "try_at_home=Torch circuit" --data-urlencode "action=publish" "$BASE/studio/lessons/$LESSON"
check "publish with all sections works"  "published" "$($MYSQL -N -e "SELECT status FROM lessons WHERE id=$LESSON")"
PAGE=$(curl -s -b "$TJ" "$BASE/lessons/$LESSON")
check "watch page shows real-life cards" "Where you see it in Malawi" "$PAGE"
check "user text is escaped"             "&lt;script&gt;alert(1)&lt;/script&gt;" "$PAGE"
check "tutor downloads identical bytes"  "$SHA" "$(curl -s -b "$TJ" "$BASE/lessons/$LESSON/file" | sha256sum | cut -d' ' -f1)"

echo "Who can watch"
check "guest is sent to login"           "303" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/lessons/$LESSON/file")"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
$MYSQL -e "INSERT INTO users (role, full_name, phone, password_hash) VALUES ('student', 'Stu Dent', '+265888777666', '$HASH')"
login "$SJ" 0888777666 student-pass-1
check "student sees published lesson"    "Ohm&#039;s law &amp; circuits" "$(curl -s -b "$SJ" "$BASE/lessons")"
check "student downloads the file"       "$SHA" "$(curl -s -b "$SJ" "$BASE/lessons/$LESSON/file" | sha256sum | cut -d' ' -f1)"
check "student cannot use upload API"    "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" -X POST "$BASE/studio/uploads")"
check "student cannot open studio"       "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" "$BASE/studio/lessons")"
T=$(token "$TJ" "$BASE/studio/lessons/$LESSON")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" "$BASE/studio/lessons/$LESSON/unpublish"
check "unpublished lesson hidden (404)"  "404" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" "$BASE/lessons/$LESSON")"
check "unpublished file hidden (404)"    "404" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" "$BASE/lessons/$LESSON/file")"
T=$(token "$TJ" "$BASE/studio/lessons/$LESSON")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" "$BASE/studio/lessons/$LESSON/delete"
check "deleted lesson gone"              "0" "$($MYSQL -N -e "SELECT COUNT(*) FROM lessons WHERE id=$LESSON")"
check "deleted lesson files removed"     "yes" "$([[ ! -d "$STORAGE/lessons/$LESSON" ]] && echo yes || echo no)"

echo
echo "Passed: $PASS  Failed: $FAIL"
exit $(( FAIL > 0 ))
