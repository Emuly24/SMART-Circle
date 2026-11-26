#!/usr/bin/env bash
# End-to-end test of messages, announcements, the private room and the tutor's student overview.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-support.sh
# Uses Form 3 students +26599600000x.
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
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM conversations WHERE subject LIKE 'E2E %' OR student_id IN (SELECT id FROM users WHERE phone LIKE '+26599600000%')"
sql "DELETE FROM users WHERE phone LIKE '+26599600000%'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
for n in 1 2; do
    NAME=$([[ $n == 1 ]] && echo "Alinafe Support" || echo "Bwalo Support")
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at) VALUES ('student', '$NAME', '+26599600000$n', 'female', '$HASH', NOW())"
    sql "INSERT INTO student_profiles (user_id, form, track, guardian_name, guardian_phone, agreement_accepted_at, guardian_confirmed_at) SELECT id, 3, 'science', 'Mrs Phiri', '+265888000111', NOW(), NOW() FROM users WHERE phone='+26599600000$n'"
done
AID=$(sql "SELECT id FROM users WHERE phone='+265996000001'"); BID=$(sql "SELECT id FROM users WHERE phone='+265996000002'")
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
A="$WORK/a.txt"; B="$WORK/b.txt"; TJ="$WORK/t.txt"
login "$A" 0996000001 student-pass-1; login "$B" 0996000002 student-pass-1; login "$TJ" 0999123456 correct-horse-1

echo "Messages: student to tutor"
check "inbox opens"                        "Write to the tutor" "$(curl -s -b "$A" "$BASE/messages")"
NEW=$(curl -s -b "$A" "$BASE/messages/new")
check "  only the tutor can be chosen"     "optgroup label=\"Tutors\"" "$NEW"
refute "  no other students listed"        "Bwalo Support" "$NEW"
R=$(post "$A" /messages/new /messages --data-urlencode to="$TID" --data-urlencode "subject=E2E question about moles" --data-urlencode "body=I am stuck on moles <b>help</b>")
check "student sends a message"            "303" "$R"
CID=$(sql "SELECT id FROM conversations WHERE subject='E2E question about moles'")
check "  writing to another student refused" "You can write to the tutor" "$(post "$A" /messages/new /messages --data-urlencode to="$BID" --data-urlencode "subject=E2E peer" --data-urlencode "body=hi" >/dev/null; cat "$WORK/last.html")"
check "  nothing stored for the peer try"  "0" "$(sql "SELECT COUNT(*) FROM conversations WHERE subject='E2E peer'")"
check "  empty message refused"            "Write a message first" "$(post "$A" /messages/new /messages --data-urlencode to="$TID" --data-urlencode "subject=E2E empty" --data-urlencode "body=  " >/dev/null; cat "$WORK/last.html")"
check "tutor is notified"                  "New message: E2E question about moles" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "header shows 1 unread"              'class="nav-count"' "$(curl -s -b "$TJ" "$BASE/dashboard")"
check "tutor inbox shows it unread"        "inbox-row--unread" "$(curl -s -b "$TJ" "$BASE/messages")"
T=$(curl -s -b "$TJ" "$BASE/messages/$CID")
check "tutor reads it (escaped)"           "I am stuck on moles &lt;b&gt;help&lt;/b&gt;" "$T"
refute "  read now"                        "inbox-row--unread" "$(curl -s -b "$TJ" "$BASE/messages")"
check "student sees Seen by the tutor"     "Seen by" "$(curl -s -b "$A" "$BASE/messages/$CID")"
check "tutor replies"                      "303" "$(post "$TJ" "/messages/$CID" "/messages/$CID/reply" --data-urlencode "body=Let us look at it on Saturday.")"
check "  student notified"                 "New reply: E2E question about moles" "$(curl -s -b "$A" "$BASE/notifications")"
check "  reply shows in the thread"        "Let us look at it on Saturday." "$(curl -s -b "$A" "$BASE/messages/$CID")"
check "another student cannot open it"     "404" "$(code -b "$B" "$BASE/messages/$CID")"
check "  nor reply to it"                  "404" "$(post "$B" /messages "/messages/$CID/reply" --data-urlencode "body=sneaky")"
check "  nothing added"                    "2" "$(sql "SELECT COUNT(*) FROM messages WHERE conversation_id=$CID")"
check "guests are sent to log in"          "303" "$(code "$BASE/messages")"
check "no way to delete a message"         "404" "$(code -X POST "$BASE/messages/$CID/delete")"

echo "Announcements"
check "students cannot announce"           "403" "$(post "$A" /messages /messages/announce --data-urlencode audience=students --data-urlencode "subject=E2E fake" --data-urlencode "body=x" | cut -c1-3)"
check "tutor announces to Form 3"          "303" "$(post "$TJ" /messages/new /messages/announce --data-urlencode audience=form:3 --data-urlencode "subject=E2E Saturday revision" --data-urlencode "body=Revision on Saturday at 9.")"
ANN=$(sql "SELECT id FROM conversations WHERE subject='E2E Saturday revision'")
check "  both Form 3 students are members" "2" "$(sql "SELECT COUNT(*) FROM conversation_members WHERE conversation_id=$ANN AND user_id IN ($AID, $BID)")"
check "  students notified"                "Announcement: E2E Saturday revision" "$(curl -s -b "$B" "$BASE/notifications")"
AV=$(curl -s -b "$B" "$BASE/messages/$ANN")
check "  student reads it"                 "Revision on Saturday at 9." "$AV"
check "  offered a private reply"          "Reply privately to the tutor" "$AV"
refute "  no reply-all box"                'name="body"' "$AV"
check "  replying to all refused"          "You cannot reply here" "$(post "$B" "/messages/$ANN" "/messages/$ANN/reply" --data-urlencode "body=to everyone" >/dev/null; cat "$WORK/last.html")"
check "  bad audience refused"             "Choose who should get it" "$(post "$TJ" /messages/new /messages/announce --data-urlencode audience=everyone --data-urlencode "subject=E2E bad" --data-urlencode "body=x" >/dev/null; cat "$WORK/last.html")"

echo "Private room"
SP=$(curl -s -b "$A" "$BASE/support")
check "room explains the rules"            "cannot be deleted" "$SP"
check "  Child Helpline 116"               'href="tel:116"' "$SP"
check "student opens the room"             "303" "$(post "$A" /support /support --data-urlencode "body=E2E I cannot study at home, it is too noisy.")"
RID=$(sql "SELECT id FROM conversations WHERE kind='support' AND student_id=$AID")
check "  one room, opened by the student"  "student" "$(sql "SELECT opened_by FROM conversations WHERE id=$RID")"
check "  tutor notified"                   "Private room: Alinafe Support would like to talk" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  not in the ordinary inbox"        "0" "$(curl -s -b "$A" "$BASE/messages" | grep -c "too noisy" || true)"
check "  /messages/{id} sends to the room" "/support" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$A" "$BASE/messages/$RID")"
check "tutor sees the room list"           "Alinafe Support" "$(curl -s -b "$TJ" "$BASE/studio/support")"
check "tutor reads the room"               "too noisy" "$(curl -s -b "$TJ" "$BASE/studio/support/$RID")"
check "tutor replies in the room"          "303" "$(post "$TJ" "/studio/support/$RID" "/studio/support/$RID" --data-urlencode "body=Thank you for telling me. Could you study at the school library?")"
check "  student sees the reply"           "school library" "$(curl -s -b "$A" "$BASE/support")"
check "  a second message joins the room"  "303" "$(post "$A" /support /support --data-urlencode "body=Yes, I will try.")"
check "  still one room"                   "1" "$(sql "SELECT COUNT(*) FROM conversations WHERE kind='support' AND student_id=$AID")"
check "other students cannot see it"       "0" "$(curl -s -b "$B" "$BASE/support" | grep -c "too noisy" || true)"
check "  nor open it by number"            "404" "$(code -b "$B" "$BASE/messages/$RID")"
check "students kept out of studio rooms"  "403" "$(code -b "$B" "$BASE/studio/support/$RID")"
echo "Tutor invites a student"
check "tutor invites Bwalo"                "303" "$(post "$TJ" "/studio/students/$BID" "/studio/students/$BID/invite" --data-urlencode "body=Hello Bwalo, I noticed you seem tired lately. Is everything all right?")"
BRID=$(sql "SELECT id FROM conversations WHERE kind='support' AND student_id=$BID")
check "  opened by the tutor"              "tutor" "$(sql "SELECT opened_by FROM conversations WHERE id=$BRID")"
check "  student notified"                 "Your tutor has invited you to talk privately" "$(curl -s -b "$B" "$BASE/notifications")"
check "  student sees the invitation"      "The tutor invited you" "$(curl -s -b "$B" "$BASE/support")"
RP=$(curl -s -b "$TJ" "$BASE/studio/support/$BRID")
check "  guardian WhatsApp prefilled"      "https://wa.me/265888000111?text=Dear%20Mrs%20Phiri" "$RP"
refute "  guardian text has no content"    "seem%20tired" "$RP"
check "  mark guardian told"               "303" "$(post "$TJ" "/studio/support/$BRID" "/studio/support/$BRID/guardian")"
check "  recorded"                         "1" "$(sql "SELECT guardian_notified_at IS NOT NULL FROM conversations WHERE id=$BRID")"
check "  page says so"                     "Guardian told about the invitation" "$(curl -s -b "$TJ" "$BASE/studio/support/$BRID")"

echo "Student overview"
O=$(curl -s -b "$TJ" "$BASE/studio/students")
check "lists the students"                 "Alinafe Support" "$O"
check "students cannot open it"            "403" "$(code -b "$A" "$BASE/studio/students")"
sql "UPDATE users SET last_login_at = NOW() - INTERVAL 10 DAY WHERE id=$BID"
sql "INSERT INTO assessments (kind, title, form, subject, status, closes_at) VALUES ('assignment', 'E2E support missed', 3, 'chemistry', 'published', NOW() - INTERVAL 2 DAY)"
F=$(curl -s -b "$TJ" "$BASE/studio/students?show=flagged")
if [[ "$(sql "SELECT COUNT(*) FROM terms WHERE CURDATE() BETWEEN opens_on AND closes_on")" != "0" ]]; then
    check "quiet student flagged"          "Not seen for over a week" "$F"
fi
check "missed work flagged"                "1 piece of work missed this month" "$F"
S=$(curl -s -b "$TJ" "$BASE/studio/students/$AID")
check "detail: call link"                  'href="tel:+265996000001"' "$S"
check "  WhatsApp link"                    'https://wa.me/265996000001?text=Hello%20Alinafe' "$S"
check "  SMS link"                         'href="sms:+265996000001?body=' "$S"
check "  guardian call"                    'href="tel:+265888000111"' "$S"
check "  message button"                   "/messages/new?to=$AID" "$S"
check "log a phone call"                   "303" "$(post "$TJ" "/studio/students/$AID" "/studio/students/$AID/contact" --data-urlencode who=guardian --data-urlencode channel=phone --data-urlencode "summary=E2E talked about a quiet place to study")"
check "  shown in the log"                 "[Guardian] E2E talked about a quiet place to study" "$(curl -s -b "$TJ" "$BASE/studio/students/$AID")"
check "  empty note refused"               "Write a short note" "$(post "$TJ" "/studio/students/$AID" "/studio/students/$AID/contact" --data-urlencode channel=phone --data-urlencode "summary=" >/dev/null; cat "$WORK/last.html")"
check "unknown student is 404"             "404" "$(code -b "$TJ" "$BASE/studio/students/999999")"
check "nothing changes on GET"             "1" "$(sql "SELECT COUNT(*) FROM contact_log WHERE person_type='user' AND person_id=$AID")"

echo "Flood guard"
# Dated like the server's own clock (the app's connection may use another time zone than this client).
for i in $(seq 1 30); do sql "INSERT INTO messages (conversation_id, user_id, body, created_at) SELECT $CID, $AID, 'E2E flood $i', MAX(created_at) FROM messages"; done
check "31st message in an hour waits"      "wait a little" "$(post "$A" "/messages/$CID" "/messages/$CID/reply" --data-urlencode "body=one more" >/dev/null; cat "$WORK/last.html")"

sql "DELETE FROM assessments WHERE title LIKE 'E2E support %'"
echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
