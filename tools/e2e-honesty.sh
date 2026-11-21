#!/usr/bin/env bash
# End-to-end test of the honesty rule: lessons and topics open only for what each student's own
# school has taught. Locks, unlock notifications, students' own reports (open at once where no
# teacher reports, checked where one does), "my class hasn't reached it", early access, the
# tutor's checks, readiness ranking, stale reports and the honesty report.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-honesty.sh
# Uses Form 1 students (phones +26599400000x) and schools named "E2E Honesty ...".
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
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
report() { post "$1" "/topics/$2" "/topics/$2/report" --data-urlencode "status=$3" >/dev/null; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM users WHERE phone LIKE '+26599400000%'"
sql "DELETE FROM lessons WHERE title LIKE 'E2E Honesty%'; DELETE FROM schools WHERE name LIKE 'E2E Honesty%'"
sql "DELETE FROM topic_requests WHERE topic_id IN (SELECT id FROM topics WHERE form=1)"
sql "INSERT INTO schools (name, district) VALUES ('E2E Honesty North', 'Mzimba'), ('E2E Honesty South', 'Nsanje')"
NORTH=$(sql "SELECT id FROM schools WHERE name='E2E Honesty North'"); SOUTH=$(sql "SELECT id FROM schools WHERE name='E2E Honesty South'")
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
student() { # phone name school
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', '$2', '$1', 'female', '$HASH')"
    sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 1, 'general', $3, NOW(), NOW() FROM users WHERE phone='$1'"
}
student +265994000001 "Nora North" "$NORTH"
student +265994000002 "Nathan North" "$NORTH"
student +265994000003 "Sara South" "$SOUTH"
N1="$WORK/n1.txt"; N2="$WORK/n2.txt"; S1="$WORK/s1.txt"; TJ="$WORK/t.txt"; PJ="$WORK/p.txt"
login "$N1" 0994000001 student-pass-1; login "$N2" 0994000002 student-pass-1; login "$S1" 0994000003 student-pass-1
login "$TJ" 0999123456 correct-horse-1
N1ID=$(sql "SELECT id FROM users WHERE phone='+265994000001'"); N2ID=$(sql "SELECT id FROM users WHERE phone='+265994000002'")
S1ID=$(sql "SELECT id FROM users WHERE phone='+265994000003'")

# South has a partner who teaches Physics; North has none.
post "$TJ" /studio/partners /studio/partners --data-urlencode "full_name=Paul South" --data-urlencode phone=0994000009 \
    --data-urlencode "school_name=E2E Honesty South" --data-urlencode district=Nsanje --data-urlencode "subjects[]=physics" >/dev/null
PID=$(sql "SELECT id FROM users WHERE phone='+265994000009'")
PCODE=$(curl -s -b "$TJ" "$BASE/studio/partners/$PID" | grep -oE 'class="reference">[^<]+' | sed 's/.*>//; s/ //g')
T=$(token "$PJ" "$BASE/welcome"); curl -s -o /dev/null -b "$PJ" -c "$PJ" --data-urlencode "_token=$T" --data-urlencode phone=0994000009 --data-urlencode "code=$PCODE" "$BASE/welcome"
T=$(token "$PJ" "$BASE/welcome/password"); curl -s -o /dev/null -b "$PJ" -c "$PJ" --data-urlencode "_token=$T" --data-urlencode password=partner-pass-1 --data-urlencode password_confirm=partner-pass-1 "$BASE/welcome/password"

topic() { sql "SELECT id FROM topics WHERE form=1 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1 OFFSET $1"; }
T1=$(topic 0); T2=$(topic 1); T3=$(topic 2); T4=$(topic 3); T5=$(topic 4)
T1SUB=$(sql "SELECT COALESCE((SELECT id FROM topics WHERE parent_id=$T1 ORDER BY sort_order LIMIT 1), $T1)")
T1NAME=$(sql "SELECT title FROM topics WHERE id=$T1"); T4NAME=$(sql "SELECT title FROM topics WHERE id=$T4")

# A published lesson on a subtopic of T1.
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
sql "INSERT INTO lessons (title, form, subject, topic_id, why_it_matters, where_in_malawi, careers, try_at_home, status, published_at, sha256, duration_ms, size_bytes, created_by)
     VALUES ('E2E Honesty lesson', 1, 'physics', $T1SUB, 'w', 'm', 'c', 't', 'published', NOW(), REPEAT('b', 64), 30000, 10, $TID)"
LID=$(sql "SELECT id FROM lessons WHERE title='E2E Honesty lesson'")

echo "Locked until the school has taught it"
check "lesson list shows it locked"        "🔒 Not taught at your school yet" "$(curl -s -b "$S1" "$BASE/lessons" | tr -d '\n' | grep -oP 'lesson-card--locked.*?</a>' || true)"
check "lesson page refuses (403)"          "403" "$(code -b "$S1" "$BASE/lessons/$LID")"
check "  and explains why"                 "opens for you as soon as your school" "$(curl -s -b "$S1" "$BASE/lessons/$LID")"
check "lesson file refuses (403)"          "403" "$(code -b "$S1" "$BASE/lessons/$LID/file")"
check "topic page says not taught"         "Not taught at your school yet" "$(curl -s -b "$S1" "$BASE/topics/$T1SUB")"
check "topic list marks it"                "🔒 Not yet" "$(curl -s -b "$S1" "$BASE/topics?subject=physics")"
check "other forms' lessons not listed"    "0" "$(curl -s -b "$S1" "$BASE/lessons" | grep -c 'Ohm' || true)"

echo "School report unlocks, with a notification"
post "$PJ" "/partner/coverage?form=1&subject=physics" /partner/coverage --data-urlencode form=1 --data-urlencode subject=physics --data-urlencode "status[$T1]=covered" >/dev/null
check "student told it unlocked"           "Your school has covered: $T1NAME" "$(curl -s -b "$S1" "$BASE/notifications")"
check "  and that a lesson is ready"       "A lesson is ready for you" "$(curl -s -b "$S1" "$BASE/notifications")"
check "lesson opens (200)"                 "200" "$(code -b "$S1" "$BASE/lessons/$LID")"
check "  page says who it is for"          "For students whose school has taught this topic" "$(curl -s -b "$S1" "$BASE/lessons/$LID")"
check "  view recorded: basis school"      "school" "$(sql "SELECT access_basis FROM lesson_views WHERE user_id=$S1ID AND lesson_id=$LID")"
check "North still locked (other school)"  "403" "$(code -b "$N1" "$BASE/lessons/$LID")"
check "North not notified"                 "0" "$(sql "SELECT COUNT(*) FROM notifications WHERE user_id=$N1ID AND type='topic_unlocked'")"

echo "My class hasn't reached it"
report "$S1" "$T1SUB" not_yet
check "locks again for that student"       "403" "$(code -b "$S1" "$BASE/lessons/$LID")"
check "  topic says so"                    "Your class has not reached it yet" "$(curl -s -b "$S1" "$BASE/topics/$T1SUB")"
report "$S1" "$T1SUB" clear
check "undo opens it again"                "200" "$(code -b "$S1" "$BASE/lessons/$LID")"

echo "Students' own reports"
report "$N1" "$T1" covered
check "no teacher reports: opens at once"  "200" "$(code -b "$N1" "$BASE/lessons/$LID")"
check "  marked as unchecked"              "The tutor will check with your school" "$(curl -s -b "$N1" "$BASE/topics/$T1")"
check "  view recorded: basis self"        "self" "$(sql "SELECT access_basis FROM lesson_views WHERE user_id=$N1ID AND lesson_id=$LID")"
check "  only for that student"            "403" "$(code -b "$N2" "$BASE/lessons/$LID")"
report "$S1" "$T2" covered
check "contradicts the teacher: stays shut" "Waiting for the tutor to check" "$(curl -s -b "$S1" "$BASE/topics/$T2")"
check "  tutor notified"                   "Coverage check" "$(curl -s -b "$TJ" "$BASE/notifications")"
report "$N2" "$T1" not_yet
C=$(curl -s -b "$TJ" "$BASE/studio/checks")
check "checks: unchecked report listed"    "No teacher reports this subject" "$C"
check "checks: contradiction listed"       "Contradicts the school" "$C"
check "checks: classmates disagree"        "Nathan North: not yet" "$C"
check "students cannot open checks"        "403" "$(code -b "$N1" "$BASE/studio/checks")"

echo "Tutor decisions"
post "$TJ" /studio/checks /studio/checks/report --data-urlencode "user_id=$S1ID" --data-urlencode "topic_id=$T2" --data-urlencode decision=confirm >/dev/null
check "confirm opens it for the student"   "Your class has covered it (confirmed)" "$(curl -s -b "$S1" "$BASE/topics/$T2")"
post "$TJ" /studio/checks /studio/checks/report --data-urlencode "user_id=$N1ID" --data-urlencode "topic_id=$T1" --data-urlencode decision=confirm_school >/dev/null
check "confirm for whole school recorded"  "covered tutor" "$(sql "SELECT status, source FROM topic_coverage WHERE school_id=$NORTH AND topic_id=$T1")"
check "  class-not-yet still wins"         "403" "$(code -b "$N2" "$BASE/lessons/$LID")"
report "$N2" "$T1" clear
check "  classmate opens after undo"       "200" "$(code -b "$N2" "$BASE/lessons/$LID")"
report "$N2" "$T3" covered
post "$TJ" /studio/checks /studio/checks/report --data-urlencode "user_id=$N2ID" --data-urlencode "topic_id=$T3" --data-urlencode decision=reject >/dev/null
check "rejected report closes it again"    "🔒" "$(curl -s -b "$N2" "$BASE/topics/$T3")"
check "  student told"                     "Not confirmed" "$(curl -s -b "$N2" "$BASE/notifications")"

echo "Early access"
post "$N2" "/topics/$T5" "/topics/$T5/request" --data-urlencode struggle_type=idea --data-urlencode "details=I want to start early on this" \
    --data-urlencode "exception_reason=Our physics teacher is on leave all term" >/dev/null
check "pending early access"               "pending" "$(sql "SELECT status FROM topic_exceptions WHERE user_id=$N2ID AND topic_id=$T5")"
check "listed in checks with reason"       "on leave all term" "$(curl -s -b "$TJ" "$BASE/studio/checks")"
post "$TJ" /studio/checks /studio/checks/exception --data-urlencode "user_id=$N2ID" --data-urlencode "topic_id=$T5" --data-urlencode decision=decline >/dev/null
check "decline: student told"              "Early access declined" "$(curl -s -b "$N2" "$BASE/notifications")"
check "  still locked"                     "Not taught at your school yet" "$(curl -s -b "$N2" "$BASE/topics/$T5")"
sql "INSERT INTO lessons (title, form, subject, topic_id, why_it_matters, where_in_malawi, careers, try_at_home, sha256, duration_ms, size_bytes, created_by)
     VALUES ('E2E Honesty early lesson', 1, 'physics', NULL, 'w', 'm', 'c', 't', REPEAT('c', 64), 30000, 10, $TID)"
L2=$(sql "SELECT id FROM lessons WHERE title='E2E Honesty early lesson'")
post "$TJ" "/studio/lessons/$L2" "/studio/lessons/$L2" --data-urlencode "title=E2E Honesty early lesson" --data-urlencode form=1 --data-urlencode subject=physics \
    --data-urlencode "topic_id=$T5" --data-urlencode why_it_matters=w --data-urlencode where_in_malawi=m --data-urlencode careers=c --data-urlencode try_at_home=t --data-urlencode action=publish >/dev/null
check "lesson for a locked asker: waits"   "It opens for you when your school has taught this topic" "$(curl -s -b "$N2" "$BASE/notifications")"
check "  and the lesson stays shut"        "403" "$(code -b "$N2" "$BASE/lessons/$L2")"

echo "Readiness and ranking"
post "$TJ" "/studio/schools/$NORTH/coverage?form=1&subject=physics" "/studio/schools/$NORTH/coverage" --data-urlencode form=1 --data-urlencode subject=physics --data-urlencode "status[$T4]=covered" >/dev/null
post "$PJ" "/partner/coverage?form=1&subject=physics" /partner/coverage --data-urlencode form=1 --data-urlencode subject=physics --data-urlencode "status[$T1]=covered" --data-urlencode "status[$T4]=covering" >/dev/null
post "$S1" "/topics/$T1SUB" "/topics/$T1SUB/request" --data-urlencode struggle_type=calculations --data-urlencode "details=I cannot convert the units" >/dev/null
R=$(curl -s -b "$TJ" "$BASE/studio/requests?form=1&subject=physics" | tr -d '\n')
check "ready to record lists taught topic" "$T4NAME" "$(grep -oE 'ready-list.*</ul>' <<<"$R" || true)"
check "  with readiness 3/3"               "3/3 taught" "$R"
FIRST=$(grep -oE 'href="/studio/requests/[0-9]+"' <<<"$R" | head -1 | grep -oE '[0-9]+')
check "taught request ranked first"        "$T1SUB" "$(sql "SELECT topic_id FROM topic_requests WHERE id=$FIRST")"
check "voice shows its basis"              "Taught at school" "$(curl -s -b "$TJ" "$BASE/studio/requests/$FIRST")"

echo "Out-of-date reports"
refute "fresh report: no reminder"         "weeks since the last update" "$(curl -s -b "$PJ" "$BASE/dashboard")"
sql "UPDATE topic_coverage SET reported_at = NOW() - INTERVAL 30 DAY WHERE school_id=$SOUTH; UPDATE schools SET coverage_checked_at = NULL WHERE id=$SOUTH"
check "after 4 weeks: reminder"            "4 weeks since the last update" "$(curl -s -b "$PJ" "$BASE/dashboard")"
check "  tutor sees it out of date"        "Out of date" "$(curl -s -b "$TJ" "$BASE/studio/honesty" | tr -d '\n' | grep -oE 'E2E Honesty South.{0,200}' || true)"
post "$PJ" /dashboard /partner/coverage/checked >/dev/null
refute "nothing changed clears it"         "weeks since the last update" "$(curl -s -b "$PJ" "$BASE/dashboard")"
check "  topics stay open"                 "200" "$(code -b "$S1" "$BASE/lessons/$LID")"

echo "Honesty report"
H=$(curl -s -b "$TJ" "$BASE/studio/honesty")
check "report loads"                       "lesson views were on topics the student" "$H"
check "  counts the self-reported view"    "Student’s class had covered it" "$H"
check "  lists early access decisions"     "on leave all term" "$H"
check "students cannot open it"            "403" "$(code -b "$N1" "$BASE/studio/honesty")"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
