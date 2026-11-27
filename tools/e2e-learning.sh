#!/usr/bin/env bash
# End-to-end test of the weekly Quick 5, anonymous termly feedback, the impact report and offline saving.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-learning.sh
# Uses students +26598400000x at "E2E Learning School". Today must fall inside a term of the seeded
# 2026/27 calendar for the feedback checks (true in term time until July 2027).
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"
cleanup() {
    # Leave no applications behind: other suites reset intakes.
    $MYSQL -N -e "DELETE FROM users WHERE phone LIKE '+26598400000%'; DELETE FROM applications WHERE reference LIKE 'E2E-L%'; DELETE FROM questions WHERE prompt LIKE 'E2E learning %'" || true
    rm -rf "$WORK"
}
trap cleanup EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM questions WHERE prompt LIKE 'E2E learning %'; DELETE FROM users WHERE phone LIKE '+26598400000%'; DELETE FROM schools WHERE name='E2E Learning School'; DELETE FROM applications WHERE reference LIKE 'E2E-L%'"
TERM=$(sql "SELECT id FROM terms WHERE CURDATE() BETWEEN opens_on AND closes_on LIMIT 1")
[[ -n "$TERM" ]] && sql "DELETE FROM term_feedback WHERE term_id=$TERM AND helpful LIKE 'E2E%'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Learning School', 'Ntcheu')"
SCH=$(sql "SELECT id FROM schools WHERE name='E2E Learning School'")
INTAKE=$(sql "SELECT id FROM intakes ORDER BY id DESC LIMIT 1")
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
for n in 1 2 3 4; do
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at) VALUES ('student', 'Learning Student $n', '+26598400000$n', 'female', '$HASH', NOW())"
    sql "INSERT INTO applications (intake_id, reference, full_name, gender, form, track, phone, motivation, guardian_name, guardian_phone, status, quiz_score, quiz_total) VALUES ($INTAKE, 'E2E-L$n', 'Learning Student $n', 'female', 2, 'general', '+26598400000$n', 'x', 'G', '+265888000000', 'admitted', 6, 10)"
    sql "INSERT INTO student_profiles (user_id, application_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT u.id, a.id, 2, 'general', $SCH, NOW(), NOW() FROM users u, applications a WHERE u.phone='+26598400000$n' AND a.reference='E2E-L$n'"
done
S1=$(sql "SELECT id FROM users WHERE phone='+265984000001'")
# Six maths topics taught at the school; one more topic NOT taught.
TAUGHT=$(sql "SELECT GROUP_CONCAT(id) FROM (SELECT id FROM topics WHERE form=2 AND subject='mathematics' AND is_active=1 AND parent_id IS NULL ORDER BY sort_order LIMIT 6) t")
UNTAUGHT=$(sql "SELECT id FROM topics WHERE form=2 AND subject='mathematics' AND is_active=1 AND parent_id IS NULL ORDER BY sort_order DESC LIMIT 1")
for t in ${TAUGHT//,/ }; do
    sql "INSERT IGNORE INTO topic_coverage (school_id, topic_id, status) VALUES ($SCH, $t, 'covered')"
    sql "INSERT INTO questions (form, subject, topic_id, type, prompt, data, marks) VALUES (2, 'mathematics', $t, 'mcq', 'E2E learning What is 2 + 2? (topic $t)', '{\"options\":[\"3\",\"4\",\"5\"],\"correct\":1}', 1)"
done
sql "INSERT INTO questions (form, subject, topic_id, type, prompt, data, marks) VALUES (2, 'mathematics', $UNTAUGHT, 'mcq', 'E2E learning SECRET untaught topic', '{\"options\":[\"a\",\"b\"],\"correct\":0}', 1)"
sql "INSERT INTO questions (form, subject, topic_id, type, prompt, data, marks) VALUES (2, 'mathematics', $(echo $TAUGHT | cut -d, -f1), 'short_text', 'E2E learning SHORT written answer', '{\"model_answer\":\"x\"}', 1)"
A="$WORK/a.txt"; TJ="$WORK/t.txt"; login "$A" 0984000001 student-pass-1; login "$TJ" 0999123456 correct-horse-1

echo "Quick 5"
Q=$(curl -s -b "$A" "$BASE/quick5")
check "page opens"                         "This week’s questions" "$Q"
check "  five questions"                   "5" "$(grep -c 'class="card quiz-q"' <<<"$Q")"
refute "  never an untaught topic"         "SECRET untaught" "$Q"
refute "  only auto-marked questions"      "SHORT written" "$Q"
refute "  answers are not in the page"     '"correct"' "$Q"
check "  same set when reloaded"           "$(grep -oE 'answer\[[0-9]+\]' <<<"$Q" | sort -u | tr '\n' ' ')" "$(curl -s -b "$A" "$BASE/quick5" | grep -oE 'answer\[[0-9]+\]' | sort -u | tr '\n' ' ')"
IDS=$(grep -oE 'answer\[[0-9]+\]' <<<"$Q" | sort -u | grep -oE '[0-9]+')
ARGS=(); first=1; for id in $IDS; do if [[ $first == 1 ]]; then ARGS+=(--data-urlencode "answer[$id]=0"); first=0; else ARGS+=(--data-urlencode "answer[$id]=1"); fi; done
R=$(post "$A" /quick5 /quick5 "${ARGS[@]}" >/dev/null; cat "$WORK/last.html")
check "check answers: 4 of 5"              "4 of 5 right" "$R"
check "  the wrong one shows the answer"   "The answer: 4." "$R"
check "  encouraging wording"              "Not yet" "$R"
check "  recorded this week"               "5 4" "$(sql "SELECT COUNT(*), SUM(correct) FROM quick5_attempts WHERE user_id=$S1")"
check "  streak of 1 week"                 '<span class="stat-tile__n">1</span><span>week in a row' "$(curl -s -b "$A" "$BASE/quick5")"
check "  done this week, extra practice"   "Here are five more for extra practice" "$(curl -s -b "$A" "$BASE/quick5")"
check "  answered ones are not repeated"   "0" "$(comm -12 <(echo "$IDS" | sort) <(curl -s -b "$A" "$BASE/quick5" | grep -oE 'answer\[[0-9]+\]' | grep -oE '[0-9]+' | sort -u) | wc -l | tr -d ' ')"
check "dashboard has Quick 5"              'href="/quick5"' "$(curl -s -b "$A" "$BASE/dashboard")"
check "admins cannot take it"              "403" "$(code -b "$TJ" "$BASE/quick5")"

if [[ -n "$TERM" ]]; then
echo "Anonymous termly feedback"
check "dashboard asks for feedback"        "How are the lessons going?" "$(curl -s -b "$A" "$BASE/dashboard")"
check "  promises anonymity"               "Your answers are anonymous" "$(curl -s -b "$A" "$BASE/feedback")"
check "  answers are required"             "Choose how clear" "$(post "$A" /feedback /feedback --data-urlencode pace=right >/dev/null; cat "$WORK/last.html")"
check "send feedback"                      "303" "$(post "$A" /feedback /feedback --data-urlencode clarity=4 --data-urlencode pace=fast --data-urlencode "helpful=E2E the worked examples" --data-urlencode "change=E2E slower please" | cut -c1-3)"
check "  stored without any user"          "0" "$(sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='term_feedback' AND column_name LIKE '%user%'")"
check "  asked only once"                  "already given feedback" "$(post "$A" /feedback /feedback --data-urlencode clarity=1 --data-urlencode pace=slow >/dev/null; cat "$WORK/last.html")"
refute "  no longer on the dashboard"      "How are the lessons going?" "$(curl -s -b "$A" "$BASE/dashboard")"
FB=$(curl -s -b "$TJ" "$BASE/studio/feedback?term=$TERM")
check "admin sees the count"               "answers" "$FB"
refute "  comments hidden below 3 answers" "E2E the worked examples" "$FB"
for n in 2 3; do J="$WORK/s$n.txt"; login "$J" 098400000$n student-pass-1; post "$J" /feedback /feedback --data-urlencode clarity=5 --data-urlencode pace=right --data-urlencode "helpful=E2E practice questions $n" >/dev/null; done
FB=$(curl -s -b "$TJ" "$BASE/studio/feedback?term=$TERM")
check "  shown from 3 answers"             "E2E the worked examples" "$FB"
check "  change suggestions too"           "E2E slower please" "$FB"
check "students cannot read the report"    "403" "$(code -b "$A" "$BASE/studio/feedback")"
fi

echo "Impact"
I=$(curl -s -b "$TJ" "$BASE/studio/impact")
check "lists students"                     "Learning Student 1" "$I"
check "  entry quiz as a percentage"       "60%" "$I"
check "  Quick 5 accuracy"                 "80% of 5" "$I"
check "  honest about its limits"          "A guide, not an exact measure" "$I"
check "students cannot open it"            "403" "$(code -b "$A" "$BASE/studio/impact")"

echo "Offline"
check "service worker served"              "sc-offline-v1" "$(curl -s "$BASE/sw.js")"
check "  installable app manifest"         '"start_url": "/dashboard"' "$(curl -s "$BASE/manifest.webmanifest")"
check "  offline page"                     "You are offline" "$(curl -s "$BASE/offline.html")"
check "  manifest linked"                  'rel="manifest"' "$(curl -s -b "$A" "$BASE/dashboard")"
LID=$(sql "SELECT id FROM lessons WHERE status='published' LIMIT 1")
if [[ -n "$LID" ]]; then
    check "  lesson page offers saving"    "js-offline-save" "$(curl -s -b "$TJ" "$BASE/lessons/$LID")"
fi
check "  logging out clears saved lessons" "caches.delete('sc-offline-v1')" "$(curl -s "$BASE/assets/js/site.js")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
