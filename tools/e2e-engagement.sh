#!/usr/bin/env bash
# End-to-end test of seat activity checks (automatic nudges, the tutor's check-in page, pausing, freeing a seat),
# editable message templates, and the weekly digest (honesty rule included).
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-engagement.sh
# Uses students +26599820000x, schools "E2E Seats ...", applications "SC-E2E-SEAT*" and lessons "E2E Digest ...".
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
cleanup() {
    sql "DELETE FROM users WHERE phone LIKE '+26599820000%'; DELETE FROM applications WHERE reference LIKE 'SC-E2E-SEAT%'"
    sql "DELETE FROM lessons WHERE title LIKE 'E2E Digest %'; DELETE FROM schools WHERE name LIKE 'E2E Seats %'"
    sql "DELETE FROM document_templates WHERE template_key IN ('guardian_admission_en', 'student_nudge')"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM users WHERE phone LIKE '+26599820000%'; DELETE FROM applications WHERE reference LIKE 'SC-E2E-SEAT%'; DELETE FROM lessons WHERE title LIKE 'E2E Digest %'; DELETE FROM schools WHERE name LIKE 'E2E Seats %'"
sql "DELETE FROM document_templates WHERE template_key IN ('guardian_admission_en', 'student_nudge')"
sql "INSERT INTO schools (name, district) VALUES ('E2E Seats Taught', 'Ntcheu'), ('E2E Seats Untaught', 'Dedza')"
TAUGHT=$(sql "SELECT id FROM schools WHERE name='E2E Seats Taught'"); UNTAUGHT=$(sql "SELECT id FROM schools WHERE name='E2E Seats Untaught'")
TOPIC=$(sql "SELECT id FROM topics WHERE form=1 AND subject='mathematics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($TAUGHT, $TOPIC, 'covered', 'tutor')"
HASH=$(php -r 'echo password_hash("seat-pass-1", PASSWORD_DEFAULT);')
INTAKE=$(sql "SELECT MAX(id) FROM intakes")
student() { # phone name gender school last_login_days_ago(or NULL) admitted_days_ago
    local login=NULL; [[ "$5" != NULL ]] && login="NOW() - INTERVAL $5 DAY"
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at, created_at) VALUES ('student', '$2', '$1', '$3', '$HASH', $login, NOW() - INTERVAL $6 DAY)"
    sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_name, guardian_phone, guardian_email, guardian_language, guardian_token, created_at)
         SELECT id, 1, 'general', $4, NOW(), 'Mayi Seat', '+265888000333', 'seat.guardian@example.com', 'en', LPAD(id, 24, 'b'), NOW() - INTERVAL $6 DAY FROM users WHERE phone='$1'"
}
student +265998200001 "Seat Quiet" female "$TAUGHT" 30 60
student +265998200002 "Seat Drifting" female "$TAUGHT" 16 60
student +265998200003 "Seat Busy" female "$TAUGHT" 1 60
student +265998200004 "Seat Never" male "$UNTAUGHT" NULL 25
student +265998200005 "Seat New" male "$UNTAUGHT" NULL 3
id() { sql "SELECT id FROM users WHERE phone='$1'"; }
QUIET=$(id +265998200001); DRIFT=$(id +265998200002); BUSY=$(id +265998200003); NEVER=$(id +265998200004); NEW=$(id +265998200005)
sql "INSERT INTO applications (intake_id, reference, status, full_name, gender, form, track, phone, motivation, guardian_name, guardian_phone, user_id)
     VALUES ($INTAKE, 'SC-E2E-SEAT1', 'admitted', 'Seat Quiet', 'female', 1, 'general', '+265998200001', 'x', 'Mayi', '+265888000333', $QUIET),
            ($INTAKE, 'SC-E2E-SEAT9', 'waitlisted', 'Seat Waiting', 'female', 1, 'general', '+265998200009', 'x', 'Mayi', '+265888000334', NULL)"
TJ="$WORK/t.txt"; SJ="$WORK/s.txt"; BJ="$WORK/b.txt"
login "$TJ" 0999123456 correct-horse-1; login "$SJ" 0998200001 seat-pass-1; login "$BJ" 0998200003 seat-pass-1
sql "UPDATE users SET last_login_at = NOW() - INTERVAL 30 DAY WHERE id = $QUIET"   # logging in for the test must not count

echo "Automatic nudges (daily, after a page visit)"
sql "UPDATE scheduled_jobs SET last_run_at = NULL WHERE name = 'seat_checks'"
curl -s -o /dev/null "$BASE/about"
check "quiet 30 days: nudged"              "nudge" "$(sql "SELECT action FROM seat_checks WHERE user_id=$QUIET")"
check "quiet 16 days: nudged"              "nudge" "$(sql "SELECT action FROM seat_checks WHERE user_id=$DRIFT")"
check "never active (25 days): nudged"     "nudge" "$(sql "SELECT action FROM seat_checks WHERE user_id=$NEVER")"
check "active student left alone"          "0" "$(sql "SELECT COUNT(*) FROM seat_checks WHERE user_id=$BUSY")"
check "newly admitted left alone"          "0" "$(sql "SELECT COUNT(*) FROM seat_checks WHERE user_id=$NEW")"
check "the student sees a kind nudge"      "We miss you, Seat!" "$(curl -s -b "$SJ" "$BASE/notifications")"
check "job recorded"                       "Nudged 3 quiet students" "$(sql "SELECT last_result FROM scheduled_jobs WHERE name='seat_checks'")"
sql "UPDATE scheduled_jobs SET last_run_at = NULL WHERE name = 'seat_checks'"
curl -s -o /dev/null "$BASE/about"
check "not nudged again within 14 days"    "1" "$(sql "SELECT COUNT(*) FROM seat_checks WHERE user_id=$QUIET")"

echo "The tutor's seat activity page"
P=$(curl -s -b "$TJ" "$BASE/studio/seats")
check "page opens"                         "Seat activity" "$P"
check "students page links to it"          'href="/studio/seats"' "$(curl -s -b "$TJ" "$BASE/studio/students")"
check "students kept out (403)"            "403" "$(code -b "$SJ" "$BASE/studio/seats")"
check "needs check-in: 2"                  "Need your check-in (2)" "$P"
check "  quiet 30 days listed"             "Last active 30 days ago" "$P"
check "  never active listed"              "Not active since being admitted 25 days ago" "$P"
check "  drifting under quiet 14+"         "Quiet for 14+ days (1)" "$P"
refute "  busy student not listed"         "Seat Busy" "$P"
check "  waiting list shown"               "1 person on the waiting list" "$P"
post "$TJ" /studio/seats "/studio/seats/$DRIFT/nudge" >/dev/null
check "tutor nudge recorded"               "Nudged by the tutor" "$(sql "SELECT note FROM seat_checks WHERE user_id=$DRIFT ORDER BY id DESC LIMIT 1")"

echo "Pausing"
check "pause needs a future date"          "422" "$(post "$TJ" /studio/seats "/studio/seats/$NEVER/pause" --data-urlencode until=2020-01-01 --data-urlencode "reason=Sick")"
check "  explained"                        "Choose a date within the next four months" "$(cat "$WORK/last.html")"
UNTIL=$(date -d '+10 days' +%F)
post "$TJ" /studio/seats "/studio/seats/$NEVER/pause" --data-urlencode "until=$UNTIL" --data-urlencode "reason=No phone until the end of the month" >/dev/null
P=$(curl -s -b "$TJ" "$BASE/studio/seats")
check "paused"                             "Paused (1)" "$P"
check "  with the reason"                  "No phone until the end of the month" "$P"
check "  no longer needs check-in"         "Need your check-in (1)" "$P"

echo "Freeing a seat"
check "needs a reason"                     "422" "$(post "$TJ" /studio/seats "/studio/seats/$QUIET/release" --data-urlencode "reason=")"
post "$TJ" /studio/seats "/studio/seats/$QUIET/release" --data-urlencode "reason=Moved to another district; no reply for a month" >/dev/null
check "seat freed"                         "left disabled withdrawn" "$(sql "SELECT sp.status, u.status, a.status FROM users u JOIN student_profiles sp ON sp.user_id=u.id JOIN applications a ON a.user_id=u.id WHERE u.id=$QUIET")"
check "  reason kept"                      "released" "$(sql "SELECT action FROM seat_checks WHERE user_id=$QUIET ORDER BY id DESC LIMIT 1")"
check "  audited"                          "seat_released" "$(sql "SELECT action FROM audit_log ORDER BY id DESC LIMIT 1")"
check "  their account is off"             "303" "$(code -b "$SJ" "$BASE/dashboard")"
refute "  off the seat page"               "Seat Quiet" "$(curl -s -b "$TJ" "$BASE/studio/seats")"

echo "Message templates"
check "list"                               "Friendly nudge to a quiet student" "$(curl -s -b "$TJ" "$BASE/studio/templates")"
check "settings links to them"             'href="/studio/templates"' "$(curl -s -b "$TJ" "$BASE/studio/settings")"
check "students kept out (403)"            "403" "$(code -b "$BJ" "$BASE/studio/templates")"
check "unknown template (404)"             "404" "$(code -b "$TJ" "$BASE/studio/templates/nope")"
check "guardian link cannot be removed"    "422" "$(post "$TJ" /studio/templates/guardian_admission_en /studio/templates/guardian_admission_en --data-urlencode "subject=Hi" --data-urlencode "body=Hello {{guardian}}")"
check "  explained"                        "must keep {{link}}" "$(cat "$WORK/last.html")"
check "unknown placeholder refused"        "422" "$(post "$TJ" /studio/templates/guardian_admission_en /studio/templates/guardian_admission_en --data-urlencode "subject=Hi {{nickname}}" --data-urlencode "body={{link}}")"
post "$TJ" /studio/templates/guardian_admission_en /studio/templates/guardian_admission_en --data-urlencode "subject=Welcome {{child_first}}" \
    --data-urlencode "body=E2E-WORDING Dear {{guardian}}, {{child_first}} has joined SMART Circle. Please confirm here: {{link}}" >/dev/null
check "saved"                              "Your wording" "$(curl -s -b "$TJ" "$BASE/studio/templates/guardian_admission_en")"
check "  preview filled in"                "E2E-WORDING Dear Mayi Phiri, Chikondi has joined" "$(curl -s -b "$TJ" "$BASE/studio/templates/guardian_admission_en")"
G=$(curl -s -b "$TJ" "$BASE/studio/guardians/$BUSY")
check "guardian page uses it"              "E2E-WORDING Dear Mayi Seat, Seat has joined" "$G"
check "  with the real link"               "/g/" "$G"
post "$TJ" /studio/templates/guardian_admission_en /studio/templates/guardian_admission_en/reset >/dev/null
check "reset to default"                   "this is " "$(curl -s -b "$TJ" "$BASE/studio/guardians/$BUSY" | grep -o 'Hello Mayi Seat, this is ' | head -1)"
post "$TJ" /studio/templates/student_nudge /studio/templates/student_nudge --data-urlencode "subject=E2E-NUDGE Come back, {{student}}" --data-urlencode "body=We saved your seat." >/dev/null
post "$TJ" /studio/seats "/studio/seats/$DRIFT/nudge" >/dev/null
login "$WORK/d.txt" 0998200002 seat-pass-1
check "nudge uses the new wording"         "E2E-NUDGE Come back, Seat" "$(curl -s -b "$WORK/d.txt" "$BASE/notifications")"

echo "Weekly digest"
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
sql "INSERT INTO lessons (title, form, subject, topic_id, status, created_by, published_at, sha256) VALUES ('E2E Digest Fractions', 1, 'mathematics', $TOPIC, 'published', $TID, NOW(), REPEAT('0', 64))"
LES=$(sql "SELECT id FROM lessons WHERE title='E2E Digest Fractions'")
sql "INSERT INTO lesson_views (user_id, lesson_id, last_watched_at) VALUES ($BUSY, $LES, NOW() - INTERVAL 2 DAY)"
check "digest button on the email page"   "Send the digest now" "$(curl -s -b "$TJ" "$BASE/studio/mail")"
check "students cannot send it (403)"      "403" "$(code -b "$BJ" -X POST "$BASE/studio/digest/send")"
post "$TJ" /studio/mail /studio/digest/send >/dev/null
check "sent"                               "Weekly digest sent to" "$(curl -s -b "$TJ" "$BASE/studio/mail")"
N=$(curl -s -b "$BJ" "$BASE/notifications")
check "student hears what is new for them" "1 new lesson for you: E2E Digest Fractions" "$N"
check "  and is praised for studying"      "Well done for studying last week" "$(sql "SELECT body FROM notifications WHERE user_id=$BUSY AND type='digest' ORDER BY id DESC LIMIT 1")"
refute "honesty: untaught school not told" "E2E Digest Fractions" "$(sql "SELECT IFNULL(GROUP_CONCAT(body), '') FROM notifications WHERE user_id=$NEW AND type='digest'")"
check "  but still gets a digest"          "Nothing new this week" "$(sql "SELECT body FROM notifications WHERE user_id=$NEW AND type='digest' ORDER BY id DESC LIMIT 1")"
check "freed seat gets nothing"            "0" "$(sql "SELECT COUNT(*) FROM notifications WHERE user_id=$QUIET AND type='digest'")"
check "tutor gets a summary"               "Waiting for you:" "$(sql "SELECT body FROM notifications WHERE user_id=$TID AND type='digest' ORDER BY id DESC LIMIT 1")"
check "  including quiet students"         "quiet student(s) to check on" "$(sql "SELECT body FROM notifications WHERE user_id=$TID AND type='digest' ORDER BY id DESC LIMIT 1")"
sql "DELETE FROM notifications WHERE type IN ('digest', 'nudge') AND user_id=$TID"

echo; echo "Engagement: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
