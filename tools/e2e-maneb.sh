#!/usr/bin/env bash
# End-to-end test of MANEB revision mode, results sharing, the end of the year, alumni and office hours.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-maneb.sh
# Uses students +26599000000x. Adds a temporary national MSCE date ten days ahead (removed at the end).
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
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
cleanup() {
    sql "DELETE FROM calendar_events WHERE title LIKE 'E2E MANEB%'; DELETE FROM lessons WHERE title LIKE 'E2E revision %'; DELETE FROM office_hours WHERE note LIKE 'E2E%'; DELETE FROM community_posts WHERE title LIKE 'E2E %'"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM calendar_events WHERE title LIKE 'E2E MANEB%'; DELETE FROM lessons WHERE title LIKE 'E2E revision %'; DELETE FROM office_hours WHERE note LIKE 'E2E%'; DELETE FROM community_posts WHERE title LIKE 'E2E %'"
sql "DELETE FROM users WHERE phone LIKE '+26599000000%'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
mk() { # n name form track
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at) VALUES ('student', '$2', '+26599000000$1', 'female', '$HASH', NOW())"
    sql "INSERT INTO student_profiles (user_id, form, track, guardian_phone, agreement_accepted_at, guardian_confirmed_at) SELECT id, $3, '$4', '+265888000222', NOW(), NOW() FROM users WHERE phone='+26599000000$1'"
}
mk 1 "Rhoda Revise" 4 science
mk 2 "Jabu Junior" 2 general
mk 3 "Thoko Three" 3 science
mk 4 "Una One" 1 general
uid() { sql "SELECT id FROM users WHERE phone='+26599000000$1'"; }
R1=$(uid 1); R2=$(uid 2); R3=$(uid 3); R4=$(uid 4)
J1="$WORK/1.txt"; J2="$WORK/2.txt"; J3="$WORK/3.txt"; J4="$WORK/4.txt"; TJ="$WORK/t.txt"
login "$J1" 0990000001 student-pass-1; login "$J2" 0990000002 student-pass-1; login "$J3" 0990000003 student-pass-1; login "$J4" 0990000004 student-pass-1
login "$TJ" 0999123456 correct-horse-1
T3=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND is_active=1 AND parent_id IS NULL ORDER BY sort_order, id LIMIT 1")
sql "INSERT INTO lessons (title, form, subject, topic_id, status, sha256, published_at) VALUES ('E2E revision Form 3 physics', 3, 'physics', $T3, 'published', REPEAT('a', 64), NOW())"
L3=$(sql "SELECT id FROM lessons WHERE title='E2E revision Form 3 physics'")

echo "Before revision opens"
check "revision page explains the rule"   "three weeks before" "$(curl -s -b "$J1" "$BASE/revision")"
check "Form 4 cannot browse Form 3"        "Form 4 syllabus" "$(curl -s -b "$J1" "$BASE/topics?form=3&subject=physics")"
refute "  Form 3 topic not shown"          "/topics/$T3\"" "$(curl -s -b "$J1" "$BASE/topics?form=3&subject=physics")"
check "  Form 3 lesson closed"             "4" "$(code -b "$J1" "$BASE/lessons/$L3" | cut -c1)"

echo "Revision mode (MSCE in 10 days)"
export TZ=Africa/Blantyre  # the site's own time zone
START=$(date -d '+10 days' +%F); END=$(date -d '+25 days' +%F)
sql "INSERT INTO calendar_events (kind, title, forms, starts_on, ends_on) VALUES ('msce', 'E2E MANEB MSCE', '4', '$START', '$END')"
RV=$(curl -s -b "$J1" "$BASE/revision")
check "plan shows the countdown"           "<strong>10</strong> days to the first paper" "$RV"
check "  Form 3 and Form 4 open"           "Every Form 3 and Form 4 topic" "$RV"
check "  pacing hint"                      "lesson" "$RV"
check "dashboard banner"                   "MANEB revision is open: 10 days to go" "$(curl -s -b "$J1" "$BASE/dashboard")"
TP=$(curl -s -b "$J1" "$BASE/topics?form=3&subject=physics")
check "Form 4 now browses Form 3"          "Form 3 syllabus" "$TP"
check "  revision note on topics"          "MANEB revision is open" "$TP"
check "  topic opened for revision"        "Open for your MANEB revision" "$(curl -s -b "$J1" "$BASE/topics/$T3")"
check "lesson opens"                       "200" "$(code -b "$J1" "$BASE/lessons/$L3")"
check "  logged as revision"               "revision" "$(sql "SELECT access_basis FROM lesson_views WHERE user_id=$R1 AND lesson_id=$L3")"
check "  shown in the honesty report"      "MANEB revision" "$(curl -s -b "$TJ" "$BASE/studio/honesty")"
check "lesson list includes Form 3"        "E2E revision Form 3 physics" "$(curl -s -b "$J1" "$BASE/lessons")"
refute "Form 3 student: no revision"       "MANEB revision is open" "$(curl -s -b "$J3" "$BASE/dashboard")"
refute "Form 2 student: no revision"       "MANEB revision is open" "$(curl -s -b "$J2" "$BASE/dashboard")"
check "tutor sees who is revising"         "Rhoda Revise" "$(curl -s -b "$TJ" "$BASE/studio/results")"
sql "DELETE FROM calendar_events WHERE title LIKE 'E2E MANEB%'"
check "closes again without the date"      "4" "$(code -b "$J1" "$BASE/lessons/$L3" | cut -c1)"

echo "MANEB results"
check "Form 1 cannot record results"       "403" "$(code -b "$J4" "$BASE/results/mine")"
check "Form 2 records JCE"                 "303" "$(post "$J2" /results/mine /results/mine --data-urlencode exam=jce --data-urlencode year=$(date +%Y) --data-urlencode "grades[mathematics]=B" --data-urlencode "grades[english]=A" --data-urlencode share=summary)"
check "  Form 2 cannot record MSCE"        "Choose the exam" "$(post "$J2" /results/mine /results/mine --data-urlencode exam=msce --data-urlencode year=$(date +%Y) --data-urlencode "grades[mathematics]=3" >/dev/null; cat "$WORK/last.html")"
check "  wrong grades refused"             "Use the grades on the result slip" "$(post "$J1" /results/mine /results/mine --data-urlencode exam=msce --data-urlencode year=$(date +%Y) --data-urlencode "grades[mathematics]=A" >/dev/null; cat "$WORK/last.html")"
check "Form 4 records MSCE, named"         "303" "$(post "$J1" /results/mine /results/mine --data-urlencode exam=msce --data-urlencode year=$(date +%Y) --data-urlencode "grades[mathematics]=2" --data-urlencode "grades[physics]=4" --data-urlencode "grades[english]=9" --data-urlencode subjects_sat=8 --data-urlencode subjects_passed=7 --data-urlencode points=22 --data-urlencode "reflection=E2E Do past papers every week." --data-urlencode share=named)"
check "  tutor notified"                   "MANEB results shared" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  shown back to the student"        "Mathematics: 2" "$(curl -s -b "$J1" "$BASE/results/mine")"
PUB=$(curl -s "$BASE/results")
check "public page counts it"              "Physics" "$PUB"
refute "  no name before the slip is seen" "Rhoda Revise" "$PUB"
MID=$(sql "SELECT id FROM maneb_results WHERE user_id=$R1")
check "tutor checks the slip"              "303" "$(post "$TJ" /studio/results "/studio/results/$MID/verify")"
PUB=$(curl -s "$BASE/results")
check "  name and advice now public"       "Rhoda Revise" "$PUB"
check "  advice escaped and shown"         "E2E Do past papers every week." "$PUB"
check "  English 9 is not a pass"          "0 of 1" "$PUB"
post "$J1" /results/mine /results/mine --data-urlencode exam=msce --data-urlencode year=$(date +%Y) --data-urlencode "grades[mathematics]=1" --data-urlencode "grades[physics]=4" --data-urlencode "grades[english]=9" --data-urlencode subjects_sat=8 --data-urlencode subjects_passed=7 --data-urlencode points=22 --data-urlencode share=named >/dev/null
check "changing grades clears the check"   "1" "$(sql "SELECT verified_at IS NULL FROM maneb_results WHERE id=$MID")"
check "  still one row"                    "1" "$(sql "SELECT COUNT(*) FROM maneb_results WHERE user_id=$R1")"
refute "  name hidden again"               "Rhoda Revise" "$(curl -s "$BASE/results")"
check "students cannot verify"             "403" "$(post "$J2" /results/mine "/studio/results/$MID/verify" | cut -c1-3)"

echo "End of the year"
check "year-end page lists students"       "Jabu Junior" "$(curl -s -b "$TJ" "$BASE/studio/year-end")"
check "Form 3 track required"              "Choose science" "$(post "$TJ" /studio/year-end /studio/year-end --data-urlencode "s[$R2][action]=promote" --data-urlencode "s[$R1][action]=graduate" >/dev/null; cat "$WORK/last.html")"
check "  nothing changed"                  "2 student" "$(sql "SELECT form, role FROM student_profiles sp JOIN users u ON u.id=sp.user_id WHERE u.id=$R2")"
check "  Form 3 cannot graduate"           "Only Form 4" "$(post "$TJ" /studio/year-end /studio/year-end --data-urlencode "s[$R3][action]=graduate" >/dev/null; cat "$WORK/last.html")"
# Everyone else in the database stays where they are (no choice = stay).
check "apply the year end"                 "303" "$(post "$TJ" /studio/year-end /studio/year-end --data-urlencode "s[$R2][action]=promote" --data-urlencode "s[$R2][track]=science" --data-urlencode "s[$R1][action]=graduate" --data-urlencode "s[$R3][action]=stay" --data-urlencode "s[$R4][action]=stay")"
check "  Form 2 is now Form 3 science"     "3 science" "$(sql "SELECT form, track FROM student_profiles WHERE user_id=$R2")"
check "  Form 4 joined the alumni"         "alumni graduated" "$(sql "SELECT u.role, sp.status FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=$R1")"
check "  alumni profile created"           "1" "$(sql "SELECT COUNT(*) FROM alumni_profiles WHERE user_id=$R1")"
check "  student told"                     "Welcome to Form 3" "$(curl -s -b "$J2" "$BASE/notifications")"
check "  others unchanged"                 "1" "$(sql "SELECT form FROM student_profiles WHERE user_id=$R4")"

echo "Alumni"
check "alumni dashboard"                   "Welcome back, Rhoda" "$(curl -s -b "$J1" "$BASE/dashboard")"
check "  no lessons for alumni"            "403" "$(code -b "$J1" "$BASE/lessons")"
check "  no topics for alumni"             "403" "$(code -b "$J1" "$BASE/topics")"
check "  no revision page"                 "403" "$(code -b "$J1" "$BASE/revision")"
check "  still sees their results"         "Mathematics: 1" "$(curl -s -b "$J1" "$BASE/results/mine")"
check "save what I am doing"               "303" "$(post "$J1" /alumni /alumni --data-urlencode now_doing=waiting --data-urlencode "institution=E2E hoping for MUST" --data-urlencode "needs[]=loans" --data-urlencode "needs[]=selection" --data-urlencode can_mentor=1 --data-urlencode "mentor_note=Physics revision")"
check "  tutor notified of needs"          "An alumnus asked for help" "$(curl -s -b "$TJ" "$BASE/notifications")"
AL=$(curl -s -b "$TJ" "$BASE/studio/alumni")
check "  tutor sees needs"                 "Student loans and grants" "$AL"
check "  and mentors"                      "Can mentor" "$AL"
check "students cannot open /alumni"       "403" "$(code -b "$J3" "$BASE/alumni")"
check "alumni write to the tutor"          "303" "$(post "$J1" /messages/new /messages --data-urlencode to=$(sql "SELECT id FROM users WHERE phone='+265999123456'") --data-urlencode "subject=E2E alumni hello" --data-urlencode "body=I got selected!")"
sql "DELETE FROM conversations WHERE subject='E2E alumni hello'"

echo "Alumni community"
check "alumni post a story"                "303" "$(post "$J1" /alumni/community /alumni/community --data-urlencode kind=story --data-urlencode "title=E2E How I revised" --data-urlencode "body=Little and often.")"
PID=$(sql "SELECT id FROM community_posts WHERE title='E2E How I revised'")
check "Form 3 sees the board"              "From our alumni" "$(curl -s -b "$J3" "$BASE/alumni/community")"
refute "  but not unshared posts"          "E2E How I revised" "$(curl -s -b "$J3" "$BASE/alumni/community")"
check "  nor by number"                    "404" "$(code -b "$J3" "$BASE/alumni/community/$PID")"
check "Form 1 kept out"                    "403" "$(code -b "$J4" "$BASE/alumni/community")"
check "students cannot post"               "Only alumni and the tutor" "$(post "$J3" /alumni/community /alumni/community --data-urlencode kind=question --data-urlencode "title=E2E q" --data-urlencode "body=x" >/dev/null; cat "$WORK/last.html")"
check "tutor replies"                      "303" "$(post "$TJ" "/alumni/community/$PID" "/alumni/community/$PID/reply" --data-urlencode "body=Thank you for sharing.")"
check "  author notified"                  "New reply: E2E How I revised" "$(curl -s -b "$J1" "$BASE/notifications")"
check "tutor shares with Form 3-4"         "303" "$(post "$TJ" "/alumni/community/$PID" "/alumni/community/$PID/moderate" --data-urlencode what=post --data-urlencode action=share)"
check "  Form 3 now reads it"              "Little and often." "$(curl -s -b "$J3" "$BASE/alumni/community/$PID")"
refute "  without a reply box"             'name="body"' "$(curl -s -b "$J3" "$BASE/alumni/community/$PID")"
check "tutor hides it"                     "303" "$(post "$TJ" "/alumni/community/$PID" "/alumni/community/$PID/moderate" --data-urlencode what=post --data-urlencode action=hide)"
check "  gone for alumni"                  "404" "$(code -b "$J1" "$BASE/alumni/community/$PID")"
check "  kept in the database"             "hidden" "$(sql "SELECT status FROM community_posts WHERE id=$PID")"
check "  tutor still sees it"              "hidden from alumni" "$(curl -s -b "$TJ" "$BASE/alumni/community/$PID")"
check "only the tutor moderates"           "403" "$(post "$J1" /alumni/community "/alumni/community/$PID/moderate" --data-urlencode what=post --data-urlencode action=show | cut -c1-3)"

echo "Office hours"
DAY=$(date -d '+2 days' +%F)
check "past time refused"                  "Choose a time in the future" "$(post "$TJ" /studio/office-hours /studio/office-hours --data-urlencode date=$(date -d '-1 day' +%F) --data-urlencode start=16:00 --data-urlencode end=17:00 --data-urlencode mode=phone --data-urlencode capacity=1 --data-urlencode audience=form:3 >/dev/null; cat "$WORK/last.html")"
check "tutor offers a Form 3 time"         "303" "$(post "$TJ" /studio/office-hours /studio/office-hours --data-urlencode date=$DAY --data-urlencode start=16:00 --data-urlencode end=17:00 --data-urlencode mode=whatsapp --data-urlencode capacity=1 --data-urlencode audience=form:3 --data-urlencode "note=E2E Form 3 physics")"
SID=$(sql "SELECT id FROM office_hours WHERE note='E2E Form 3 physics'")
check "  Form 3 notified"                  "Office hours:" "$(curl -s -b "$J3" "$BASE/notifications")"
check "  Form 3 sees it"                   "E2E Form 3 physics" "$(curl -s -b "$J3" "$BASE/office-hours")"
refute "  Form 1 does not"                 "E2E Form 3 physics" "$(curl -s -b "$J4" "$BASE/office-hours")"
check "Form 3 books"                       "303" "$(post "$J3" /office-hours "/office-hours/$SID/book" --data-urlencode "question=E2E Ohm's law questions")"
check "  booked"                           "You are booked" "$(curl -s -b "$J3" "$BASE/office-hours")"
check "  tutor notified"                   "Office hours booked" "$(curl -s -b "$TJ" "$BASE/notifications")"
post "$J4" /office-hours "/office-hours/$SID/book" --data-urlencode "question=E2E sneaky booking" >/dev/null
check "  Form 1 cannot book"               "0" "$(sql "SELECT COUNT(*) FROM office_hour_bookings WHERE slot_id=$SID AND user_id=$R4")"
post "$J2" /office-hours "/office-hours/$SID/book" --data-urlencode "question=E2E second student" >/dev/null
check "  full after one"                   "0" "$(sql "SELECT COUNT(*) FROM office_hour_bookings WHERE slot_id=$SID AND user_id=$R2")"
check "Form 3 cancels"                     "303" "$(post "$J3" /office-hours "/office-hours/$SID/cancel")"
check "  the place frees up"               "303" "$(post "$J2" /office-hours "/office-hours/$SID/book" --data-urlencode "question=E2E second student")"
check "  booking kept as cancelled"        "cancelled" "$(sql "SELECT status FROM office_hour_bookings WHERE slot_id=$SID AND user_id=$R3")"
SP=$(curl -s -b "$TJ" "$BASE/studio/office-hours/$SID")
check "tutor sees the question"            "E2E second student" "$SP"
check "  with WhatsApp"                    "https://wa.me/265990000002" "$SP"
BID=$(sql "SELECT id FROM office_hour_bookings WHERE slot_id=$SID AND user_id=$R2")
check "  mark done"                        "303" "$(post "$TJ" "/studio/office-hours/$SID" "/studio/office-hours/bookings/$BID" --data-urlencode status=done)"
check "  recorded"                         "done" "$(sql "SELECT status FROM office_hour_bookings WHERE id=$BID")"
check "alumni time for alumni only"        "303" "$(post "$TJ" /studio/office-hours /studio/office-hours --data-urlencode date=$DAY --data-urlencode start=18:00 --data-urlencode end=19:00 --data-urlencode mode=phone --data-urlencode capacity=3 --data-urlencode audience=alumni --data-urlencode "note=E2E loans forms")"
AID=$(sql "SELECT id FROM office_hours WHERE note='E2E loans forms'")
check "  alumni book"                      "303" "$(post "$J1" /office-hours "/office-hours/$AID/book" --data-urlencode "question=E2E HESLGB loan form")"
check "  stored"                           "1" "$(sql "SELECT COUNT(*) FROM office_hour_bookings WHERE slot_id=$AID AND user_id=$R1 AND status='booked'")"
refute "  students do not see it"          "E2E loans forms" "$(curl -s -b "$J3" "$BASE/office-hours")"
check "tutor cancels the session"          "303" "$(post "$TJ" "/studio/office-hours/$AID" "/studio/office-hours/$AID/cancel")"
check "  alumni told"                      "Office hours cancelled" "$(curl -s -b "$J1" "$BASE/notifications")"
check "students kept out of studio"        "403" "$(code -b "$J3" "$BASE/studio/office-hours")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
