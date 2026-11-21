#!/usr/bin/env bash
# End-to-end test of the topic catalogue and topic requests: browsing by form and track,
# asking / "me too", exceptions, the tutor's ranked list, planning, publishing a linked
# lesson ("Lesson ready"), notifications, and the catalogue editor.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-topics.sh
# It creates its own test students (phones +26599200000x) and removes them first if present.
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
# post JAR PAGE-WITH-FORM ACTION [--data-urlencode k=v ...]
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM users WHERE phone LIKE '+26599200000%'"
sql "DELETE FROM topic_requests; DELETE FROM notifications; DELETE FROM topics WHERE title LIKE 'E2E %'; DELETE FROM lessons WHERE title LIKE 'E2E %'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
student() { # phone name gender form track
    local id
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', '$2', '$1', '$3', '$HASH')"
    id=$(sql "SELECT id FROM users WHERE phone='$1'")
    sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_confirmed_at) VALUES ($id, $4, '$5', NOW(), NOW())"
}
student +265992000001 "Alinafe Science" female 3 science
student +265992000002 "Blessings Science" male 3 science
student +265992000003 "Chisomo Humanities" female 3 humanities
A="$WORK/a.txt"; B="$WORK/b.txt"; C="$WORK/c.txt"; TJ="$WORK/t.txt"
login "$A" 0992000001 student-pass-1; login "$B" 0992000002 student-pass-1; login "$C" 0992000003 student-pass-1
login "$TJ" 0999123456 correct-horse-1

PTOPIC=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
PSUB=$(sql "SELECT id FROM topics WHERE parent_id=$PTOPIC ORDER BY sort_order LIMIT 1")
BTOPIC=$(sql "SELECT id FROM topics WHERE form=3 AND subject='biology' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
F4TOPIC=$(sql "SELECT id FROM topics WHERE form=4 AND subject='biology' LIMIT 1")

echo "Catalogue"
check "seeded catalogue has 200+ topics"   "2" "$(sql "SELECT LEFT(COUNT(*), 1) FROM topics WHERE parent_id IS NULL")"
check "guests are sent to log in"          "303 $BASE/login" "$(code "$BASE/topics")"
P=$(curl -s -b "$A" "$BASE/topics?subject=physics")
check "science student sees Physics"       "Form 3 syllabus" "$P"
check "  topic list with subtopics"        'class="subtopics"' "$P"
check "  Physics tab offered"              "tabs__subject--physics" "$P"
H=$(curl -s -b "$C" "$BASE/topics?subject=physics")
refute "humanities student: no Physics tab" "tabs__subject--physics" "$H"
check "  falls back to Mathematics"        'tabs__subject--mathematics" href="/topics?subject=mathematics" aria-current' "$H"
check "humanities: physics topic hidden"   "404" "$(code -b "$C" "$BASE/topics/$PTOPIC")"
check "other form's topic hidden"          "404" "$(code -b "$A" "$BASE/topics/$F4TOPIC")"
check "topic page shows ask form"          "Ask for this topic" "$(curl -s -b "$A" "$BASE/topics/$PSUB")"

echo "Asking for a topic"
check "missing CSRF refused"               "403" "$(code -b "$A" --data-urlencode struggle_type=idea "$BASE/topics/$PSUB/request")"
R=$(post "$A" "/topics/$PSUB" "/topics/$PSUB/request" --data-urlencode struggle_type=idea --data-urlencode details=short)
check "incomplete request -> 422"          "422" "$R"
check "  explains what is missing"         "Say where exactly you get lost" "$(cat "$WORK/last.html")"
check "  asks about school coverage"       "whether your school has taught" "$(cat "$WORK/last.html")"
R=$(post "$A" "/topics/$PSUB" "/topics/$PSUB/request" --data-urlencode struggle_type=calculations \
    --data-urlencode "details=I get lost when the units change halfway" --data-urlencode covered=yes)
check "valid request -> back to topic"     "303 $BASE/topics/$PSUB" "$R"
check "  stored as a normal request"       "open 0" "$(sql "SELECT status, is_exception FROM topic_requests WHERE topic_id=$PSUB")"
check "  tutor notified"                   "New request" "$(sql "SELECT title FROM notifications n JOIN users u ON u.id=n.user_id WHERE u.role='tutor' ORDER BY n.id DESC LIMIT 1")"
check "  page shows the request state"     "1 student has asked" "$(curl -s -b "$A" "$BASE/topics/$PSUB")"
check "asking twice is harmless"           "already asked" "$(post "$A" "/topics/$PSUB" "/topics/$PSUB/request" --data-urlencode struggle_type=idea --data-urlencode covered=yes >/dev/null; curl -s -b "$A" "$BASE/topics/$PSUB")"
check "classmate sees Me too"              "Add my voice" "$(curl -s -b "$B" "$BASE/topics/$PSUB")"
R=$(post "$B" "/topics/$PSUB" "/topics/$PSUB/request" --data-urlencode struggle_type=exam --data-urlencode covered=yes)
check "me too without details -> ok"       "303 $BASE/topics/$PSUB" "$R"
check "  vote stored"                      "1" "$(sql "SELECT COUNT(*) FROM topic_request_votes")"
check "  list shows 2 voices"              "2 voices" "$(curl -s -b "$A" "$BASE/topics?subject=physics")"
check "humanities student cannot ask physics" "404" "$(post "$C" "/topics/$BTOPIC" "/topics/$PSUB/request" --data-urlencode struggle_type=idea --data-urlencode covered=yes)"

echo "Exceptions"
R=$(post "$C" "/topics/$BTOPIC" "/topics/$BTOPIC/request" --data-urlencode struggle_type=idea \
    --data-urlencode "details=I do not understand any of it yet" --data-urlencode covered=no)
check "not covered needs a reason"         "Explain why you need it" "$(cat "$WORK/last.html")"
post "$C" "/topics/$BTOPIC" "/topics/$BTOPIC/request" --data-urlencode struggle_type=idea \
    --data-urlencode "details=I do not understand any of it yet" --data-urlencode covered=no \
    --data-urlencode "exception_reason=Our biology teacher left this term" >/dev/null
check "exception stored as pending"        "1 pending" "$(sql "SELECT is_exception, exception_status FROM topic_requests WHERE topic_id=$BTOPIC")"
check "student sees waiting for approval"  "Waiting for the tutor to approve" "$(curl -s -b "$C" "$BASE/topics/$BTOPIC")"

echo "Tutor: ranked list"
check "students cannot open the list"      "403" "$(code -b "$A" "$BASE/studio/requests")"
L=$(curl -s -b "$TJ" "$BASE/studio/requests" | tr -d '\n')
check "list loads"                         "Topic requests" "$L"
FIRST=$(grep -oE 'href="/studio/requests/[0-9]+"' <<<"$L" | head -1 | grep -oE '[0-9]+')
EXC=$(sql "SELECT id FROM topic_requests WHERE topic_id=$BTOPIC"); REQ=$(sql "SELECT id FROM topic_requests WHERE topic_id=$PSUB")
check "pending exception ranked first"     "$EXC" "$FIRST"
check "struggle breakdown shown"           "Working <b>1</b>" "$L"
check "filter by subject"                  "Exam questions" "$(curl -s -b "$TJ" "$BASE/studio/requests?subject=physics")"
refute "  biology filtered out"            "/studio/requests/$EXC\"" "$(curl -s -b "$TJ" "$BASE/studio/requests?subject=physics")"
D=$(curl -s -b "$TJ" "$BASE/studio/requests/$REQ")
check "detail shows each voice"            "Blessings Science" "$D"
check "  with the details"                 "units change halfway" "$D"
check "plan the request"                   "303 $BASE/studio/requests/$REQ" "$(post "$TJ" "/studio/requests/$REQ" "/studio/requests/$REQ/status" --data-urlencode status=planned)"
check "  status planned"                   "planned" "$(sql "SELECT status FROM topic_requests WHERE id=$REQ")"
check "  both students notified"           "2" "$(sql "SELECT COUNT(*) FROM notifications WHERE type='request_planned'")"
check "bell shows unread count"            'bell__count">1<' "$(curl -s -b "$A" "$BASE/dashboard")"
check "notifications page lists it"        "Lesson planned" "$(curl -s -b "$A" "$BASE/notifications")"
post "$A" "/notifications" "/notifications/read" >/dev/null
refute "mark all read clears the bell"     "bell__count" "$(curl -s -b "$A" "$BASE/dashboard")"
check "approve exception"                  "303" "$(post "$TJ" "/studio/requests/$EXC" "/studio/requests/$EXC/exception" --data-urlencode decision=approve)"
check "  approved"                         "approved" "$(sql "SELECT exception_status FROM topic_requests WHERE id=$EXC")"
check "  student told"                     "Exception approved" "$(curl -s -b "$C" "$BASE/notifications")"

echo "Publishing a linked lesson answers the request"
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
sql "INSERT INTO lessons (title, sha256, duration_ms, size_bytes, created_by) VALUES ('E2E Units lesson', REPEAT('a', 64), 60000, 1000, $TID)"
LID=$(sql "SELECT id FROM lessons WHERE title='E2E Units lesson' ORDER BY id DESC LIMIT 1")
check "edit page offers topics"            "data-subject=\"physics\"" "$(curl -s -b "$TJ" "$BASE/studio/lessons/$LID")"
lesson() { post "$TJ" "/studio/lessons/$LID" "/studio/lessons/$LID" --data-urlencode "title=E2E Units lesson" --data-urlencode form=3 \
    --data-urlencode "subject=$1" --data-urlencode "topic_id=$2" --data-urlencode "why_it_matters=Prices and medicine doses" \
    --data-urlencode "where_in_malawi=Fuel at the filling station" --data-urlencode "careers=Pharmacist" \
    --data-urlencode "try_at_home=Measure your room" --data-urlencode "action=$3"; }
check "topic from another subject refused" "422" "$(lesson chemistry "$PSUB" save)"
check "  says why"                         "same form and subject" "$(cat "$WORK/last.html")"
check "publish with topic"                 "303" "$(lesson physics "$PSUB" publish)"
check "  lesson linked"                    "$PSUB" "$(sql "SELECT topic_id FROM lessons WHERE id=$LID")"
check "  request marked Lesson ready"      "recorded $LID" "$(sql "SELECT status, lesson_id FROM topic_requests WHERE id=$REQ")"
check "  students told it is ready"        "Lesson ready: E2E Units lesson" "$(curl -s -b "$B" "$BASE/notifications")"
check "  topic page lists the lesson"      "E2E Units lesson" "$(curl -s -b "$A" "$BASE/topics/$PSUB")"
check "  parent topic lists it too"        "E2E Units lesson" "$(curl -s -b "$A" "$BASE/topics/$PTOPIC")"
check "  catalogue shows 1 lesson"         "1 lesson" "$(curl -s -b "$A" "$BASE/topics?subject=physics")"
student +265992000004 "Dalitso Science" male 3 science; D4="$WORK/d.txt"; login "$D4" 0992000004 student-pass-1
post "$D4" "/topics/$PSUB" "/topics/$PSUB/request" --data-urlencode struggle_type=practical --data-urlencode covered=yes >/dev/null
check "still stuck after lesson reopens"   "open" "$(sql "SELECT status FROM topic_requests WHERE id=$REQ")"

echo "Tutor: topic catalogue"
check "catalogue editor loads"             "Topic catalogue" "$(curl -s -b "$TJ" "$BASE/studio/topics?form=3&subject=physics")"
check "students cannot edit topics"        "403" "$(code -b "$A" "$BASE/studio/topics")"
check "add a topic"                        "303" "$(post "$TJ" "/studio/topics?form=3&subject=physics" "/studio/topics" --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode "title=E2E Rockets" --data-urlencode unit=Extra)"
NEW=$(sql "SELECT id FROM topics WHERE title='E2E Rockets'")
check "  student sees it"                  "E2E Rockets" "$(curl -s -b "$A" "$BASE/topics?subject=physics")"
check "add a subtopic"                     "303" "$(post "$TJ" "/studio/topics" "/studio/topics" --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode "parent_id=$NEW" --data-urlencode "title=E2E Thrust")"
check "  under its parent"                 "$NEW" "$(sql "SELECT parent_id FROM topics WHERE title='E2E Thrust'")"
check "subtopic of other subject refused"  "422" "$(post "$TJ" "/studio/topics" "/studio/topics" --data-urlencode form=3 --data-urlencode subject=chemistry --data-urlencode "parent_id=$NEW" --data-urlencode "title=E2E Wrong")"
check "paste a list"                       "303" "$(post "$TJ" "/studio/topics" "/studio/topics/import" --data-urlencode form=2 --data-urlencode subject=english \
    --data-urlencode "list=$(printf 'Unit 9: E2E Reported speech (Statements, Questions)\nE2E Idioms')")"
check "  2 topics added"                   "2" "$(sql "SELECT COUNT(*) FROM topics WHERE title LIKE 'E2E %' AND form=2")"
check "  with 2 subtopics"                 "Statements, Questions" "$(sql "SELECT GROUP_CONCAT(c.title ORDER BY c.sort_order SEPARATOR ', ') FROM topics c JOIN topics p ON p.id=c.parent_id WHERE p.title='E2E Reported speech'")"
check "hide a topic"                       "303" "$(post "$TJ" "/studio/topics/$NEW" "/studio/topics/$NEW" --data-urlencode "title=E2E Rockets" --data-urlencode sort_order=999)"
refute "  hidden from students"            "E2E Rockets" "$(curl -s -b "$A" "$BASE/topics?subject=physics")"
check "  hidden topic page is 404"         "404" "$(code -b "$A" "$BASE/topics/$NEW")"
check "  tutor still sees it (Hidden)"     "Hidden" "$(curl -s -b "$TJ" "$BASE/studio/topics?form=3&subject=physics")"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
