#!/usr/bin/env bash
# End-to-end test of exams, assignments and exercises: question bank, builder, publishing (with the
# honesty rule), taking with a server-side timer, automatic and tutor marking, releasing results.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-assessments.sh
# Uses Form 2 students (phones +26599500000x) and schools named "E2E Exams ...".
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
sql "DELETE FROM users WHERE phone LIKE '+26599500000%'"
sql "DELETE FROM assessments WHERE title LIKE 'E2E %'; DELETE FROM questions WHERE prompt LIKE 'E2E %'; DELETE FROM schools WHERE name LIKE 'E2E Exams%'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Exams East', 'Machinga'), ('E2E Exams West', 'Mchinji')"
EAST=$(sql "SELECT id FROM schools WHERE name='E2E Exams East'"); WEST=$(sql "SELECT id FROM schools WHERE name='E2E Exams West'")
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
student() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', '$2', '$1', 'male', '$HASH')"
    sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 2, 'general', $3, NOW(), NOW() FROM users WHERE phone='$1'"
}
student +265995000001 "Esau East" "$EAST"
student +265995000002 "Wezi West" "$WEST"
E="$WORK/e.txt"; W="$WORK/w.txt"; TJ="$WORK/t.txt"
login "$E" 0995000001 student-pass-1; login "$W" 0995000002 student-pass-1; login "$TJ" 0999123456 correct-horse-1
EID=$(sql "SELECT id FROM users WHERE phone='+265995000001'")
TA=$(sql "SELECT id FROM topics WHERE form=2 AND subject='mathematics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
TB=$(sql "SELECT id FROM topics WHERE form=2 AND subject='mathematics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1 OFFSET 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($EAST, $TA, 'covered', 'tutor')"

echo "Question bank"
check "bank loads"                         "Question bank" "$(curl -s -b "$TJ" "$BASE/studio/questions?form=2&subject=mathematics")"
check "students kept out"                  "403" "$(code -b "$E" "$BASE/studio/questions")"
newq() { post "$TJ" "/studio/questions/new?form=2&subject=mathematics" /studio/questions --data-urlencode form=2 --data-urlencode subject=mathematics "$@"; }
check "incomplete question -> 422"         "422" "$(newq --data-urlencode type=mcq --data-urlencode prompt=hi --data-urlencode marks=1)"
check "  explains"                         "Give at least two choices" "$(cat "$WORK/last.html")"
newq --data-urlencode type=mcq --data-urlencode "topic_id=$TA" --data-urlencode "prompt=E2E Which number is prime?" --data-urlencode marks=2 \
    --data-urlencode "options[]=9" --data-urlencode "options[]=7" --data-urlencode "options[]=15" --data-urlencode correct=1 >/dev/null
newq --data-urlencode type=numeric --data-urlencode "topic_id=$TA" --data-urlencode "prompt=E2E A minibus goes 120 km in 1.5 h. Average speed?" --data-urlencode marks=4 \
    --data-urlencode answer=80 --data-urlencode unit=km/h --data-urlencode unit_required=1 --data-urlencode tolerance_pct=1 --data-urlencode is_application=1 >/dev/null
newq --data-urlencode type=short_text --data-urlencode "topic_id=$TA" --data-urlencode "prompt=E2E Explain why speed limits matter near schools." --data-urlencode marks=3 \
    --data-urlencode "model_answer=SECRET-MODEL-ANSWER stopping distance grows with speed" >/dev/null
newq --data-urlencode type=mcq --data-urlencode "topic_id=$TB" --data-urlencode "prompt=E2E Untaught topic question" --data-urlencode marks=1 \
    --data-urlencode "options[]=a" --data-urlencode "options[]=b" --data-urlencode correct=0 >/dev/null
check "four questions saved"               "4" "$(sql "SELECT COUNT(*) FROM questions WHERE prompt LIKE 'E2E %'")"
Q1=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E Which%'"); Q2=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E A minibus%'")
Q3=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E Explain%'"); Q4=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E Untaught%'")
check "  numeric data stored"              '"answer":80,"tolerance_pct":1' "$(sql "SELECT data FROM questions WHERE id=$Q2")"
check "bank lists them"                    "E2E A minibus" "$(curl -s -b "$TJ" "$BASE/studio/questions?form=2&subject=mathematics")"

echo "Building an exam"
R=$(post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=exam --data-urlencode "title=E2E Speed test" --data-urlencode form=2 --data-urlencode subject=mathematics --data-urlencode duration_min=30)
check "create -> builder"                  "303 $BASE/studio/assessments/" "$R"
A1=$(sql "SELECT id FROM assessments WHERE title='E2E Speed test'")
check "exam needs a time limit"            "between 5 and 240" "$(post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=exam --data-urlencode "title=E2E No time" --data-urlencode form=2 --data-urlencode subject=mathematics >/dev/null; cat "$WORK/last.html")"
check "publish with no questions refused"  "Add at least one question" "$(post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/publish" >/dev/null; curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"
for q in $Q1 $Q2 $Q3; do post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/questions" --data-urlencode "question_id=$q" >/dev/null; done
check "three questions in order"           "$Q1 $Q2 $Q3" "$(sql "SELECT GROUP_CONCAT(question_id ORDER BY sort_order SEPARATOR ' ') FROM assessment_questions WHERE assessment_id=$A1")"
post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/questions/$Q3/move" --data-urlencode direction=up >/dev/null
check "reorder"                            "$Q1 $Q3 $Q2" "$(sql "SELECT GROUP_CONCAT(question_id ORDER BY sort_order SEPARATOR ' ') FROM assessment_questions WHERE assessment_id=$A1")"
OTHER=$(sql "SELECT id FROM questions WHERE subject <> 'mathematics' OR form <> 2 LIMIT 1")
if [[ -n "$OTHER" ]]; then check "other subject's question refused" "same form and subject" "$(post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/questions" --data-urlencode "question_id=$OTHER" >/dev/null; curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"; fi
check "total marks shown"                  "9 marks" "$(curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"
post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/publish" >/dev/null
check "published"                          "published" "$(sql "SELECT status FROM assessments WHERE id=$A1")"
check "taught student notified"            "New exam (timed): E2E Speed test" "$(curl -s -b "$E" "$BASE/notifications")"
refute "untaught student not notified"     "E2E Speed test" "$(curl -s -b "$W" "$BASE/notifications")"

echo "Honesty rule"
check "student list: ready to start"       "Ready to start" "$(curl -s -b "$E" "$BASE/assessments")"
check "other school: locked"               "Opens when your school has taught its topics" "$(curl -s -b "$W" "$BASE/assessments")"
check "locked student cannot start"        "303 $BASE/assessments/$A1" "$(post "$W" "/assessments/$A1" "/assessments/$A1/start")"
check "  no attempt created"               "0" "$(sql "SELECT COUNT(*) FROM submissions s JOIN users u ON u.id=s.user_id WHERE u.phone='+265995000002'")"
check "tutor uses studio, not student page" "403" "$(code -b "$TJ" "$BASE/assessments")"

echo "Taking the exam"
check "start -> take page"                 "303 $BASE/assessments/$A1/take" "$(post "$E" "/assessments/$A1" "/assessments/$A1/start")"
TK=$(curl -s -b "$E" "$BASE/assessments/$A1/take")
check "server timer shown"                 "js-exam-timer" "$TK"
SECS=$(grep -oE 'data-seconds="[0-9]+"' <<<"$TK" | grep -oE '[0-9]+')
check "  about 30 minutes left"            "yes" "$( (( SECS > 1750 && SECS <= 1800 )) && echo yes || echo "no ($SECS)")"
refute "model answer never sent"           "SECRET-MODEL-ANSWER" "$TK"
refute "correct choice not marked"         "correct" "$(grep -i 'name="answer' <<<"$TK" | tr 'A-Z' 'a-z')"
check "save part of the answers"           "303 $BASE/assessments/$A1/take" "$(post "$E" "/assessments/$A1/take" "/assessments/$A1/save" --data-urlencode "answer[$Q1]=1" --data-urlencode action=save)"
check "  saved"                            "1" "$(sql "SELECT answer FROM submission_answers sa JOIN submissions s ON s.id=sa.submission_id WHERE s.user_id=$EID AND sa.question_id=$Q1")"
check "  shown again when continuing"      "value=\"1\" checked" "$(curl -s -b "$E" "$BASE/assessments/$A1/take")"
R=$(post "$E" "/assessments/$A1/take" "/assessments/$A1/save" --data-urlencode "answer[$Q2]=80 km/h" --data-urlencode "answer[$Q3]=Children cross roads and cars need longer to stop." --data-urlencode action=submit)
check "submit"                             "303 $BASE/assessments/$A1" "$R"
S1=$(sql "SELECT id FROM submissions WHERE assessment_id=$A1 AND user_id=$EID")
check "  waits for tutor (written answer)" "submitted 6.00 9.00" "$(sql "SELECT status, score, max_score FROM submissions WHERE id=$S1")"
check "  auto marks: mcq 2, numeric 4"     "2.00 4.00" "$(sql "SELECT GROUP_CONCAT(auto_score ORDER BY question_id SEPARATOR ' ') FROM submission_answers WHERE submission_id=$S1 AND auto_score IS NOT NULL")"
check "one attempt only"                   "303 $BASE/assessments/$A1" "$(post "$E" "/assessments/$A1" "/assessments/$A1/start")"
check "  take page closed"                 "303 $BASE/assessments/$A1" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$E" "$BASE/assessments/$A1/take")"
check "questions locked once answered"     "already started" "$(post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/questions/$Q1/remove" >/dev/null; curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"
check "delete refused with answers"        "Close it instead" "$(post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/delete" >/dev/null; curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"

echo "Marking and results"
check "submissions list: to mark"          "To mark" "$(curl -s -b "$TJ" "$BASE/studio/assessments/$A1/submissions")"
M=$(curl -s -b "$TJ" "$BASE/studio/submissions/$S1")
check "marking page shows model answer"    "SECRET-MODEL-ANSWER" "$M"
check "release refused before marking"     "Mark every submission first" "$(post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/release" >/dev/null; curl -s -b "$TJ" "$BASE/studio/assessments/$A1")"
check "too-high mark refused"              "422" "$(post "$TJ" "/studio/submissions/$S1" "/studio/submissions/$S1" --data-urlencode "score[$Q3]=5")"
check "  explains"                         "from 0 to 3" "$(cat "$WORK/last.html")"
R=$(post "$TJ" "/studio/submissions/$S1" "/studio/submissions/$S1" --data-urlencode "score[$Q3]=2.5" --data-urlencode "feedback[next_step][$Q3]=Mention stopping distance." --data-urlencode "overall=Good work on units.")
check "marks saved"                        "303" "$R"
check "  total 8.5 of 9, marked"           "marked 8.50" "$(sql "SELECT status, score FROM submissions WHERE id=$S1")"
check "student waits for release"          "Marked, results coming soon" "$(curl -s -b "$E" "$BASE/assessments")"
check "  result page not yet"              "303" "$(code -b "$E" "$BASE/assessments/$A1/result")"
post "$TJ" "/studio/assessments/$A1" "/studio/assessments/$A1/release" >/dev/null
check "release -> student notified"        "Results ready: E2E Speed test" "$(curl -s -b "$E" "$BASE/notifications")"
RS=$(curl -s -b "$E" "$BASE/assessments/$A1/result")
check "result shows score"                 "8.5</strong> / 9" "$RS"
check "  and the tutor's comments"         "Mention stopping distance." "$RS"
check "  and overall feedback"             "Good work on units." "$RS"

echo "Timer is enforced on the server"
post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=exam --data-urlencode "title=E2E Quick exam" --data-urlencode form=2 --data-urlencode subject=mathematics --data-urlencode duration_min=5 >/dev/null
A2=$(sql "SELECT id FROM assessments WHERE title='E2E Quick exam'")
post "$TJ" "/studio/assessments/$A2" "/studio/assessments/$A2/questions" --data-urlencode "question_id=$Q1" >/dev/null
post "$TJ" "/studio/assessments/$A2" "/studio/assessments/$A2/publish" >/dev/null
post "$E" "/assessments/$A2" "/assessments/$A2/start" >/dev/null
sql "UPDATE submissions SET deadline_at = NOW() - INTERVAL 10 MINUTE WHERE assessment_id=$A2 AND user_id=$EID"
post "$E" "/assessments/$A2/take" "/assessments/$A2/save" --data-urlencode "answer[$Q1]=1" --data-urlencode action=submit >/dev/null
check "late answers refused"               "0.00" "$(sql "SELECT score FROM submissions WHERE assessment_id=$A2 AND user_id=$EID")"
check "  student told why"                 "Time was up" "$(curl -s -b "$E" "$BASE/assessments/$A2")"

echo "Exercise: instant results"
post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=weekly_check --data-urlencode "title=E2E Practice" --data-urlencode form=2 --data-urlencode subject=mathematics >/dev/null
A3=$(sql "SELECT id FROM assessments WHERE title='E2E Practice'")
for q in $Q1 $Q2; do post "$TJ" "/studio/assessments/$A3" "/studio/assessments/$A3/questions" --data-urlencode "question_id=$q" >/dev/null; done
post "$TJ" "/studio/assessments/$A3" "/studio/assessments/$A3/publish" >/dev/null
post "$E" "/assessments/$A3" "/assessments/$A3/start" >/dev/null
R=$(post "$E" "/assessments/$A3/take" "/assessments/$A3/save" --data-urlencode "answer[$Q1]=0" --data-urlencode "answer[$Q2]=80" --data-urlencode action=submit)
check "submit -> straight to results"      "303 $BASE/assessments/$A3/result" "$R"
RS=$(curl -s -b "$E" "$BASE/assessments/$A3/result")
check "  not yet: the answer is shown"     "The answer:</span> 7" "$RS"
check "  missing unit: half marks"         "2 / 4" "$RS"

echo "Untaught topic in an assessment"
post "$TJ" /studio/assessments/new /studio/assessments --data-urlencode kind=assignment --data-urlencode "title=E2E Mixed topics" --data-urlencode form=2 --data-urlencode subject=mathematics >/dev/null
A4=$(sql "SELECT id FROM assessments WHERE title='E2E Mixed topics'")
for q in $Q1 $Q4; do post "$TJ" "/studio/assessments/$A4" "/studio/assessments/$A4/questions" --data-urlencode "question_id=$q" >/dev/null; done
post "$TJ" "/studio/assessments/$A4" "/studio/assessments/$A4/publish" >/dev/null
check "locked until every topic is taught" "🔒 E2E Mixed topics" "$(curl -s -b "$E" "$BASE/assessments")"
post "$TJ" "/studio/schools/$EAST/coverage?form=2&subject=mathematics" "/studio/schools/$EAST/coverage" --data-urlencode form=2 --data-urlencode subject=mathematics --data-urlencode "status[$TA]=covered" --data-urlencode "status[$TB]=covered" >/dev/null
refute "opens once the school reports it"  "🔒 E2E Mixed topics" "$(curl -s -b "$E" "$BASE/assessments")"

echo "Closing"
post "$TJ" "/studio/assessments/$A4" "/studio/assessments/$A4/close" >/dev/null
check "closed: cannot start"               "303 $BASE/assessments/$A4" "$(post "$E" "/assessments/$A4" "/assessments/$A4/start")"
check "  shown as closed"                  "Closed" "$(curl -s -b "$E" "$BASE/assessments/$A4")"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
