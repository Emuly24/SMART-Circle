#!/usr/bin/env bash
# End-to-end test of the academic calendar, grades and term reports.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-grades.sh
# Uses Form 4 students +26599800000x. Marked work is inserted dated today, which must fall in a term
# of the seeded 2026/27 calendar or its following holiday (true until September 2027).
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
sql "DELETE FROM users WHERE phone LIKE '+26599800000%'"
sql "DELETE FROM assessments WHERE title LIKE 'E2E grades %'; DELETE FROM calendar_events WHERE title LIKE 'E2E %'; DELETE FROM terms WHERE year_id IN (SELECT id FROM academic_years WHERE name='2027/28'); DELETE FROM academic_years WHERE name='2027/28'"
sql "UPDATE settings SET value='{\"exercise\":15,\"assignment\":20,\"test\":25,\"exam\":40}' WHERE name='grade_weights'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', 'Grace Grades', '+265998000001', 'female', '$HASH')"
sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_confirmed_at) SELECT id, 4, 'science', NOW(), NOW() FROM users WHERE phone='+265998000001'"
GID=$(sql "SELECT id FROM users WHERE phone='+265998000001'")
G="$WORK/g.txt"; TJ="$WORK/t.txt"; login "$G" 0998000001 student-pass-1; login "$TJ" 0999123456 correct-horse-1

echo "Academic calendar"
C=$(curl -s -b "$TJ" "$BASE/studio/calendar")
check "2026/27 terms seeded"               "14 September" "$(sql "SELECT DATE_FORMAT(opens_on, '%e %M') FROM terms WHERE number=1 AND year_id=(SELECT id FROM academic_years WHERE name='2026/27')")"
check "calendar page: Term 1, 14 weeks"    "14 weeks" "$C"
check "  Term 2, 12 weeks"                 "12 weeks" "$C"
check "  MANEB JCE"                        "Junior Certificate of Education" "$C"
check "  MANEB MSCE"                       "Malawi School Certificate of Education" "$C"
check "  holidays between terms"           "Holiday" "$C"
check "dashboard shows the term"           "2026/27" "$(curl -s -b "$TJ" "$BASE/dashboard")"
check "students kept out of studio"        "403" "$(code -b "$G" "$BASE/studio/calendar")"
check "add a national date"                "303" "$(post "$TJ" /studio/calendar /studio/calendar/events --data-urlencode kind=mock --data-urlencode "title=E2E Form 4 mocks" --data-urlencode starts_on=2027-03-01 --data-urlencode ends_on=2027-03-05 --data-urlencode "forms[]=4")"
check "  end before start refused"         "on or after the start" "$(post "$TJ" /studio/calendar /studio/calendar/events --data-urlencode kind=other --data-urlencode "title=E2E bad" --data-urlencode starts_on=2027-03-05 --data-urlencode ends_on=2027-03-01 >/dev/null; cat "$WORK/last.html")"
check "save a new year"                    "303" "$(post "$TJ" /studio/calendar /studio/calendar/year --data-urlencode name=2027/28 --data-urlencode t1_open=2027-09-13 --data-urlencode t1_close=2027-12-17 --data-urlencode t2_open=2028-01-03 --data-urlencode t2_close=2028-03-24 --data-urlencode t3_open=2028-04-10 --data-urlencode t3_close=2028-07-14)"
check "  three terms stored"               "3" "$(sql "SELECT COUNT(*) FROM terms t JOIN academic_years y ON y.id=t.year_id WHERE y.name='2027/28'")"
check "  dates out of order refused"       "after the one before" "$(post "$TJ" /studio/calendar /studio/calendar/year --data-urlencode name=2028/29 --data-urlencode t1_open=2028-09-13 --data-urlencode t1_close=2028-08-01 --data-urlencode t2_open=2029-01-03 --data-urlencode t2_close=2029-03-24 --data-urlencode t3_open=2029-04-10 --data-urlencode t3_close=2029-07-14 >/dev/null; cat "$WORK/last.html")"

echo "Schools add their own dates"
PART=$(sql "SELECT u.phone FROM users u JOIN partner_profiles p ON p.user_id=u.id WHERE u.status='active' LIMIT 1")
if [[ -n "$PART" ]]; then
    PJ="$WORK/p.txt"; NEWPW=partner-cal-1
    PID=$(sql "SELECT id FROM users WHERE phone='$PART'"); sql "UPDATE users SET password_hash='$(php -r 'echo password_hash("partner-cal-1", PASSWORD_DEFAULT);')' WHERE id=$PID"
    login "$PJ" "$PART" "$NEWPW"
    check "partner sees national dates"    "Malawi School Certificate of Education" "$(curl -s -b "$PJ" "$BASE/partner/calendar")"
    check "partner adds a mid-term break"  "303" "$(post "$PJ" /partner/calendar /partner/calendar/events --data-urlencode kind=midterm --data-urlencode "title=E2E school mid-term" --data-urlencode starts_on=2027-02-15 --data-urlencode ends_on=2027-02-19)"
    check "  stored for their school"      "1" "$(sql "SELECT COUNT(*) FROM calendar_events WHERE title='E2E school mid-term' AND school_id IS NOT NULL")"
    check "  cannot add MANEB dates"       "422" "$(post "$PJ" /partner/calendar /partner/calendar/events --data-urlencode kind=jce --data-urlencode "title=E2E fake" --data-urlencode starts_on=2027-05-01)"
    NAT=$(sql "SELECT id FROM calendar_events WHERE kind='msce' AND school_id IS NULL LIMIT 1")
    post "$PJ" /partner/calendar "/partner/calendar/events/$NAT/delete" >/dev/null
    check "  cannot remove national dates" "1" "$(sql "SELECT COUNT(*) FROM calendar_events WHERE id=$NAT")"
    check "tutor sees the school's date"   "E2E school mid-term" "$(curl -s -b "$TJ" "$BASE/studio/calendar")"
fi

echo "MANEB countdown"
check "Form 4 student: days to MSCE"       "days to MANEB Malawi School Certificate" "$(curl -s -b "$G" "$BASE/dashboard")"
refute "  not the JCE"                     "Junior Certificate" "$(curl -s -b "$G" "$BASE/dashboard")"

echo "Grades"
work() { # title kind subject score max
    sql "INSERT INTO assessments (kind, title, form, subject, status, results_at) VALUES ('$2', 'E2E grades $1', 4, '$3', 'published', NOW())"
    sql "INSERT INTO submissions (assessment_id, user_id, submitted_at, status, score, max_score, marked_at) SELECT id, $GID, NOW(), 'marked', $4, $5, NOW() FROM assessments WHERE title='E2E grades $1'"
}
work ex1 weekly_check mathematics 8 10
work ex2 note_check mathematics 6 10
work as1 assignment mathematics 15 20
work te1 test mathematics 30 50
work exm exam mathematics 50 100
work phy weekly_check physics 9 10
M=$(curl -s -b "$G" "$BASE/grades")
check "maths term %: 60.5"                 "60.5%" "$M"
check "  MSCE 4, Credit"                   '<strong class="grade grade--msce">4</strong> <span class="muted">Credit' "$M"
check "  exercises averaged (70%, 2)"      "70% <span class=\"muted\">(2)" "$M"
check "physics: one exercise, 90% = 1"     '<strong class="grade grade--msce">1</strong> <span class="muted">Distinction' "$M"
check "chemistry: nothing yet"             "No marked work yet" "$M"
check "explains how it is made"            "Exams 40%" "$M"
check "overall says how many subjects"    "from 2 of 5 subjects" "$M"
B=$(curl -s -b "$TJ" "$BASE/studio/grades?form=4")
check "gradebook lists the student"        "Grace Grades" "$B"
check "  with grades"                      "<strong>4</strong> <span class=\"muted\">60.5%" "$B"
check "student detail lists every piece"   "E2E grades te1" "$(curl -s -b "$TJ" "$BASE/studio/grades/student/$GID")"
check "students cannot open the gradebook" "403" "$(code -b "$G" "$BASE/studio/grades")"

echo "Settings"
check "weights must add up to 100"         "add up to 100" "$(post "$TJ" /studio/grades/settings /studio/grades/settings --data-urlencode "weights[exercise]=50" --data-urlencode "weights[assignment]=20" --data-urlencode "weights[test]=25" --data-urlencode "weights[exam]=40" >/dev/null; cat "$WORK/last.html")"
check "bands must go down"                 "must go down" "$(post "$TJ" /studio/grades/settings /studio/grades/settings --data-urlencode "weights[exercise]=15" --data-urlencode "weights[assignment]=20" --data-urlencode "weights[test]=25" --data-urlencode "weights[exam]=40" --data-urlencode "bands[msce:2][min]=80" >/dev/null; cat "$WORK/last.html")"
post "$TJ" /studio/grades/settings /studio/grades/settings --data-urlencode "weights[exercise]=25" --data-urlencode "weights[assignment]=25" --data-urlencode "weights[test]=25" --data-urlencode "weights[exam]=25" >/dev/null
check "new weights recalculate (63.8%)"    "63.8%" "$(curl -s -b "$G" "$BASE/grades")"
post "$TJ" /studio/grades/settings /studio/grades/settings --data-urlencode "weights[exercise]=15" --data-urlencode "weights[assignment]=20" --data-urlencode "weights[test]=25" --data-urlencode "weights[exam]=40" >/dev/null

echo "Term reports"
TERM=$(sql "SELECT id FROM terms WHERE opens_on <= CURDATE() ORDER BY opens_on DESC LIMIT 1")
R=$(post "$TJ" "/studio/grades?form=4" /studio/grades/issue --data-urlencode "term=$TERM" --data-urlencode form=4 --data-urlencode "comment[$GID]=Steady progress. Keep practising units.")
check "issue reports"                      "303" "$R"
CODE=$(sql "SELECT document_code FROM term_reports WHERE user_id=$GID AND term_id=$TERM")
check "  snapshot stored with comment"     "Steady progress" "$(sql "SELECT comment FROM term_reports WHERE user_id=$GID")"
check "  student notified"                 "report is ready" "$(curl -s -b "$G" "$BASE/notifications")"
check "  listed on My grades"              "/letters/$CODE" "$(curl -s -b "$G" "$BASE/grades")"
L=$(curl -s -b "$G" "$BASE/letters/$CODE")
check "  printable report: subjects"       "Mathematics" "$L"
refute "  no PHP warnings on the letter"    "Warning</b>" "$L"
check "  with grade and comment"           "Keep practising units." "$L"
check "  verifiable"                       "Genuine" "$(curl -s "$BASE/verify/$CODE")"
check "  gradebook marks it issued"        "Report issued" "$(curl -s -b "$TJ" "$BASE/studio/grades?form=4&term=$TERM")"
post "$TJ" "/studio/grades?form=4" /studio/grades/issue --data-urlencode "term=$TERM" --data-urlencode form=4 --data-urlencode "comment[$GID]=Updated comment." >/dev/null
NEW=$(sql "SELECT document_code FROM term_reports WHERE user_id=$GID AND term_id=$TERM")
check "re-issue gives a new report"        "Updated comment." "$(curl -s -b "$G" "$BASE/letters/$NEW")"
check "  old printout marked replaced"     "replaced" "$(curl -s "$BASE/verify/$CODE")"

echo "Tests need a time limit"
check "test without time refused"          "between 5 and 240" "$(post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=test --data-urlencode "title=E2E grades untimed" --data-urlencode form=4 --data-urlencode subject=physics >/dev/null; cat "$WORK/last.html")"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
