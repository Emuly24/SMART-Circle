#!/usr/bin/env bash
# End-to-end test of quoted replies, the private-room card on dashboards, and the guided tour hooks.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-feel.sh
# Uses students +26598500000x.
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM conversations WHERE subject LIKE 'E2E feel %' OR student_id IN (SELECT id FROM users WHERE phone LIKE '+26598500000%')"
sql "DELETE FROM users WHERE phone LIKE '+26598500000%'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
for n in 1 2; do
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at) VALUES ('student', 'Feel Student $n', '+26598500000$n', 'female', '$HASH', NOW())"
    sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_confirmed_at) SELECT id, 2, 'general', NOW(), NOW() FROM users WHERE phone='+26598500000$n'"
done
S1=$(sql "SELECT id FROM users WHERE phone='+265985000001'"); S2=$(sql "SELECT id FROM users WHERE phone='+265985000002'")
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
A="$WORK/a.txt"; B="$WORK/b.txt"; TJ="$WORK/t.txt"
login "$A" 0985000001 student-pass-1; login "$B" 0985000002 student-pass-1; login "$TJ" 0999123456 correct-horse-1

echo "Quoted replies in messages"
post "$A" /messages/new /messages --data-urlencode to=$TID --data-urlencode "subject=E2E feel moles" --data-urlencode "body=How do I find the number of moles in 22 g of CO2?" >/dev/null
CID=$(sql "SELECT id FROM conversations WHERE subject='E2E feel moles'")
M1=$(sql "SELECT id FROM messages WHERE conversation_id=$CID ORDER BY id LIMIT 1")
T=$(curl -s -b "$TJ" "$BASE/messages/$CID")
check "each message has a Quote button"    "data-quote-id=\"$M1\"" "$T"
check "  works without JavaScript too"     "?quote=$M1#reply" "$T"
check "  quote.js loaded"                  "js/quote.js" "$T"
check "?quote= fills the reply form"       "value=\"$M1\" class=\"js-quote-id\"" "$(curl -s -b "$TJ" "$BASE/messages/$CID?quote=$M1")"
check "reply with a quote"                 "303" "$(post "$TJ" "/messages/$CID" "/messages/$CID/reply" --data-urlencode quote_id=$M1 --data-urlencode "body=Divide the mass by the molar mass: 22 / 44 = 0.5 mol." | cut -c1-3)"
check "  stored"                           "$M1" "$(sql "SELECT quote_id FROM messages WHERE conversation_id=$CID ORDER BY id DESC LIMIT 1")"
V=$(curl -s -b "$A" "$BASE/messages/$CID")
check "  shown above the reply"            "class=\"msg__quote\"><span class=\"msg__quote-who\">You wrote:</span> How do I find the number of moles" "$V"
post "$B" /messages/new /messages --data-urlencode to=$TID --data-urlencode "subject=E2E feel other" --data-urlencode "body=A private question from someone else" >/dev/null
OTHER=$(sql "SELECT m.id FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.subject='E2E feel other' LIMIT 1")
post "$TJ" "/messages/$CID" "/messages/$CID/reply" --data-urlencode quote_id=$OTHER --data-urlencode "body=Trying to quote another conversation" >/dev/null
check "quotes from other conversations dropped" "NULL" "$(sql "SELECT IFNULL(quote_id, 'NULL') FROM messages WHERE conversation_id=$CID ORDER BY id DESC LIMIT 1")"
refute "  nothing leaks into this thread"  "someone else" "$(curl -s -b "$A" "$BASE/messages/$CID")"
check "?quote= from another thread ignored" 'value="" class="js-quote-id"' "$(curl -s -b "$TJ" "$BASE/messages/$CID?quote=$OTHER")"

echo "Private room card on the student's dashboard"
D=$(curl -s -b "$A" "$BASE/dashboard")
check "no room yet: invite the tutor"      "Invite the tutor to talk" "$D"
check "  Child Helpline shown"             'href="tel:116"' "$D"
post "$TJ" "/studio/students/$S2" "/studio/students/$S2/invite" --data-urlencode "body=E2E feel: can we talk about your week?" >/dev/null
DB=$(curl -s -b "$B" "$BASE/dashboard")
check "tutor invited: card says so"        "The tutor would like to talk with you privately" "$DB"
check "  highlighted as new"               "room-card--unread" "$DB"
check "  links to the room"                "Open your private room" "$DB"
RID=$(sql "SELECT id FROM conversations WHERE kind='support' AND student_id=$S2")
RM=$(sql "SELECT id FROM messages WHERE conversation_id=$RID LIMIT 1")
check "student quotes the tutor in the room" "303" "$(post "$B" /support /support --data-urlencode quote_id=$RM --data-urlencode "body=Yes please, it has been hard." | cut -c1-3)"
check "  quote stored"                     "$RM" "$(sql "SELECT quote_id FROM messages WHERE conversation_id=$RID ORDER BY id DESC LIMIT 1")"
check "  tutor sees the quote"             "msg__quote" "$(curl -s -b "$TJ" "$BASE/studio/support/$RID")"
refute "  read now: not highlighted"       "room-card--unread" "$(curl -s -b "$B" "$BASE/support" >/dev/null; curl -s -b "$B" "$BASE/dashboard")"

echo "Private room card on the admin's dashboard"
TD=$(curl -s -b "$TJ" "$BASE/dashboard")
check "lists the rooms"                    "Feel Student 2" "$TD"
check "  link to all rooms"                'href="/studio/support"' "$TD"

echo "Guided tours and effects"
check "page says who and where"            'data-role="student" data-path="/dashboard"' "$D"
check "  tour button (hidden until a tour)" 'class="tour-start" hidden' "$D"
check "  tour.js loaded"                   "js/tour.js" "$D"
check "  guests too"                       'data-role="guest" data-path="/"' "$(curl -s "$BASE/")"
TJS=$(curl -s "$BASE/assets/js/tour.js")
for k in "guest:/" "student:/dashboard" "partner:/dashboard" "tutor:/dashboard" "team:/dashboard" "alumni:/dashboard"; do
    check "  a tour for $k"                "'$k'" "$TJS"
done
check "  remembers it was shown"           "localStorage" "$TJS"
CSS=$(curl -s "$BASE/assets/css/app.css")
check "effects respect reduced motion"     "prefers-reduced-motion: no-preference" "$CSS"
check "  cards ease in"                    "js-reveal" "$(curl -s "$BASE/assets/js/site.js")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
