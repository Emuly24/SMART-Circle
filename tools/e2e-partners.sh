#!/usr/bin/env bash
# End-to-end test of school partners: invitation (account, setup code, letter), account setup,
# topics-taught reports, textbooks with page references, and how both reach students and the tutor.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-partners.sh
# It removes and recreates its own test data (phones +26599300000x, schools named "E2E ...").
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
setup() { # jar phone code password
    local T; T=$(token "$1" "$BASE/welcome")
    curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "code=$3" "$BASE/welcome"
    T=$(token "$1" "$BASE/welcome/password")
    curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "password=$4" --data-urlencode "password_confirm=$4" "$BASE/welcome/password"
}

sql "DELETE FROM login_attempts"
sql "DELETE FROM users WHERE phone LIKE '+26599300000%'"
sql "DELETE FROM topic_requests; DELETE FROM textbooks WHERE title LIKE 'E2E %'; DELETE FROM schools WHERE name LIKE 'E2E %'"
TJ="$WORK/t.txt"; login "$TJ" 0999123456 correct-horse-1 >/dev/null

echo "Tutor: invite a teacher"
check "partners page loads"                "Invite a teacher" "$(curl -s -b "$TJ" "$BASE/studio/partners")"
check "students/guests kept out"           "303 $BASE/login" "$(code "$BASE/studio/partners")"
invite() { post "$TJ" /studio/partners /studio/partners --data-urlencode "full_name=$1" --data-urlencode "phone=$2" \
    --data-urlencode "school_name=$3" --data-urlencode district=Lilongwe "${@:4}"; }
check "invalid invite -> 422"              "422" "$(invite Grace 123 '' --data-urlencode subjects[]=physics)"
check "  explains"                         "first name and surname" "$(cat "$WORK/last.html")"
R=$(invite "Grace Banda" 0993000001 "E2E Kamuzu Academy" --data-urlencode subjects[]=physics --data-urlencode subjects[]=mathematics)
check "valid invite -> partner page"       "303 $BASE/studio/partners/" "$R"
PID=$(sql "SELECT id FROM users WHERE phone='+265993000001'")
P=$(curl -s -b "$TJ" "$BASE/studio/partners/$PID")
CODE=$(grep -oE 'class="reference">[^<]+' <<<"$P" | sed 's/.*>//; s/ //g')
check "setup code shown once (8 chars)"    "8" "${#CODE}"
check "role and school saved"              "partner E2E Kamuzu Academy mathematics,physics" "$(sql "SELECT u.role, s.name, pp.subjects FROM users u JOIN partner_profiles pp ON pp.user_id=u.id JOIN schools s ON s.id=pp.school_id WHERE u.id=$PID")"
LETTER=$(grep -oE 'href="/letters/[A-Z0-9]+"' <<<"$P" | head -1 | sed 's/.*letters\///; s/"//')
check "invitation letter issued"           "L" "$LETTER"
L=$(curl -s -b "$TJ" "$BASE/letters/$LETTER")
check "  letter invites as school partner" "become a <strong>school partner</strong>" "$L"
check "  names the subjects"               "Mathematics, Physics" "$L"
check "  guests cannot open it"            "404" "$(code "$BASE/letters/$LETTER")"
check "  verify page: genuine"             "Genuine" "$(curl -s "$BASE/verify/$LETTER")"
check "same phone refused"                 "already has an account" "$(invite "Grace Banda" 0993000001 "E2E Kamuzu Academy" --data-urlencode subjects[]=physics >/dev/null; cat "$WORK/last.html")"
post "$TJ" "/studio/partners/$PID" "/studio/partners/$PID/code/hide" >/dev/null
refute "code hidden after handing over"    'class="reference"' "$(curl -s -b "$TJ" "$BASE/studio/partners/$PID")"

echo "Teacher sets up the account"
PJ="$WORK/p.txt"
check "setup code -> straight to dashboard" "303 $BASE/dashboard" "$(setup "$PJ" 0993000001 "$CODE" teacher-pass-1)"
D=$(curl -s -b "$PJ" "$BASE/dashboard")
check "partner dashboard"                  "School partner" "$D"
check "  names the school"                 "E2E Kamuzu Academy" "$D"
check "  links its letter"                 "/letters/$LETTER" "$D"
check "  partner can open own letter"      "200" "$(code -b "$PJ" "$BASE/letters/$LETTER")"
check "partner cannot use student topics"  "403" "$(code -b "$PJ" "$BASE/topics")"
check "partner cannot use tutor pages"     "403" "$(code -b "$PJ" "$BASE/studio/partners")"

echo "Topics taught"
C=$(curl -s -b "$PJ" "$BASE/partner/coverage?form=3&subject=physics")
check "coverage page loads"                "Topics taught" "$C"
check "  physics tab (their subject)"      "tabs__subject--physics" "$C"
refute "  no chemistry tab"                "tabs__subject--chemistry" "$C"
check "  other subject falls back"         'tabs__subject--mathematics" href="/partner/coverage?form=1&amp;subject=mathematics" aria-current' "$(curl -s -b "$PJ" "$BASE/partner/coverage?form=1&subject=chemistry")"
T1=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
T1SUB=$(sql "SELECT id FROM topics WHERE parent_id=$T1 ORDER BY sort_order LIMIT 1")
T2=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1 OFFSET 1")
T3=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1 OFFSET 2")
CHEM=$(sql "SELECT id FROM topics WHERE form=3 AND subject='chemistry' LIMIT 1")
SCHOOL=$(sql "SELECT id FROM schools WHERE name='E2E Kamuzu Academy'")
R=$(post "$PJ" "/partner/coverage?form=3&subject=physics" /partner/coverage --data-urlencode form=3 --data-urlencode subject=physics \
    --data-urlencode "status[$T1]=covered" --data-urlencode "status[$T2]=covering" --data-urlencode "status[$T3]=" --data-urlencode "status[$CHEM]=covered")
check "save -> back to the same tab"       "303 $BASE/partner/coverage?form=3&subject=physics" "$R"
check "  covered + covering stored"        "2" "$(sql "SELECT COUNT(*) FROM topic_coverage WHERE school_id=$SCHOOL")"
check "  other subject's topic ignored"    "0" "$(sql "SELECT COUNT(*) FROM topic_coverage WHERE topic_id=$CHEM")"
check "  reported by the teacher"          "$PID" "$(sql "SELECT reported_by FROM topic_coverage WHERE topic_id=$T1")"
check "  form shows saved choice"          "name=\"status[$T1]\" value=\"covered\" checked" "$(curl -s -b "$PJ" "$BASE/partner/coverage?form=3&subject=physics")"
check "missing CSRF refused"               "403" "$(code -b "$PJ" --data-urlencode form=3 "$BASE/partner/coverage")"

echo "Textbooks and page references"
R=$(post "$PJ" /partner/textbooks /partner/textbooks --data-urlencode subject=physics --data-urlencode form=3 --data-urlencode "title=E2E Strides in Physics 3" --data-urlencode edition=2nd)
check "add a textbook"                     "303 $BASE/partner/textbooks" "$R"
BOOK=$(sql "SELECT id FROM textbooks WHERE title='E2E Strides in Physics 3'")
check "  listed for the school"            "E2E Strides in Physics 3" "$(curl -s -b "$PJ" "$BASE/partner/textbooks")"
check "  title required"                   "422" "$(post "$PJ" /partner/textbooks /partner/textbooks --data-urlencode subject=physics --data-urlencode form=3 --data-urlencode title=ab)"
R=$(post "$PJ" "/partner/textbooks/$BOOK" "/partner/textbooks/$BOOK" --data-urlencode "chapter[$T1SUB]=2" --data-urlencode "pages[$T1SUB]=14-19" --data-urlencode "chapter[$CHEM]=9")
check "save page numbers"                  "303 $BASE/partner/textbooks/$BOOK" "$R"
check "  stored for that topic only"       "2 14-19" "$(sql "SELECT chapter, pages FROM textbook_topic_refs WHERE textbook_id=$BOOK")"
check "  form shows them"                  'value="14-19"' "$(curl -s -b "$PJ" "$BASE/partner/textbooks/$BOOK")"

echo "Students see it"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', 'Kondwani E2E', '+265993000005', 'male', '$HASH')"
SID=$(sql "SELECT id FROM users WHERE phone='+265993000005'")
sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) VALUES ($SID, 3, 'science', $SCHOOL, NOW(), NOW())"
SJ="$WORK/s.txt"; login "$SJ" 0993000005 student-pass-1 >/dev/null
S=$(curl -s -b "$SJ" "$BASE/topics/$T1SUB")
check "topic page: textbook pages"         "pages 14-19" "$S"
check "  marked as used at their school"   "Used at your school" "$S"
check "  open: taught at their school"      "Taught at your school" "$S"
check "parent topic lists subtopic pages"  "(for: $(sql "SELECT title FROM topics WHERE id=$T1SUB"))" "$(curl -s -b "$SJ" "$BASE/topics/$T1")"
check "untaught topic is locked"           "Not taught at your school yet" "$(curl -s -b "$SJ" "$BASE/topics/$T3")"
R=$(post "$SJ" "/topics/$T1SUB" "/topics/$T1SUB/request" --data-urlencode struggle_type=idea --data-urlencode "details=I mix up base and derived units")
check "request on covered topic"           "303" "$R"
check "  a normal request (school said)"   "0" "$(sql "SELECT is_exception FROM topic_requests WHERE topic_id=$T1SUB")"
refute "partner never sees students' words" "I mix up" "$(curl -s -b "$PJ" "$BASE/dashboard")"
check "  topic listed without names"       "$(sql "SELECT title FROM topics WHERE id=$T1SUB")" "$(curl -s -b "$PJ" "$BASE/dashboard")"
refute "  no student names"                "Kondwani" "$(curl -s -b "$PJ" "$BASE/dashboard")"

echo "Tutor's view"
REQ=$(sql "SELECT id FROM topic_requests WHERE topic_id=$T1SUB")
check "request shows schools' coverage"    "E2E Kamuzu Academy, Lilongwe: <strong>taught" "$(curl -s -b "$TJ" "$BASE/studio/requests/$REQ")"
check "school summary page"                "Progress through the syllabus" "$(curl -s -b "$TJ" "$BASE/studio/schools/$SCHOOL")"
check "tutor can report for the school"    "303" "$(post "$TJ" "/studio/schools/$SCHOOL/coverage?form=3&subject=chemistry" "/studio/schools/$SCHOOL/coverage" --data-urlencode form=3 --data-urlencode subject=chemistry --data-urlencode "status[$CHEM]=next")"
check "  stored"                           "next" "$(sql "SELECT status FROM topic_coverage WHERE topic_id=$CHEM AND school_id=$SCHOOL")"
check "tutor sees the school's books"      "E2E Strides in Physics 3" "$(curl -s -b "$TJ" "$BASE/studio/schools/$SCHOOL/textbooks")"

echo "Another school"
invite "Peter Phiri" 0993000002 "E2E Mzuzu Secondary" --data-urlencode subjects[]=physics >/dev/null
PID2=$(sql "SELECT id FROM users WHERE phone='+265993000002'")
CODE2=$(curl -s -b "$TJ" "$BASE/studio/partners/$PID2" | grep -oE 'class="reference">[^<]+' | sed 's/.*>//; s/ //g')
P2="$WORK/p2.txt"; setup "$P2" 0993000002 "$CODE2" teacher-pass-2 >/dev/null
check "cannot open other school's book"    "404" "$(code -b "$P2" "$BASE/partner/textbooks/$BOOK")"
check "can pick the known book"            "E2E Strides in Physics 3" "$(curl -s -b "$P2" "$BASE/partner/textbooks")"
post "$P2" /partner/textbooks /partner/textbooks --data-urlencode "known_id=$BOOK" >/dev/null
check "  now linked to both schools"       "2" "$(sql "SELECT COUNT(*) FROM school_textbooks WHERE textbook_id=$BOOK")"
check "  and it can open the refs"         "14-19" "$(curl -s -b "$P2" "$BASE/partner/textbooks/$BOOK")"
check "other school sees no coverage yet"  "0/" "$(curl -s -b "$P2" "$BASE/dashboard")"

echo "Account control"
post "$TJ" "/studio/partners/$PID" "/studio/partners/$PID/status" --data-urlencode status=disabled >/dev/null
check "disabled partner is logged out"     "303 $BASE/login" "$(code -b "$PJ" "$BASE/dashboard")"
check "  and cannot log in"                "422" "$(login "$WORK/x.txt" 0993000001 teacher-pass-1)"
post "$TJ" "/studio/partners/$PID" "/studio/partners/$PID/status" --data-urlencode status=active >/dev/null
check "re-enabled partner can log in"      "303 $BASE/dashboard" "$(login "$WORK/y.txt" 0993000001 teacher-pass-1)"
post "$TJ" "/studio/partners/$PID" "/studio/partners/$PID/code" >/dev/null
NEW=$(curl -s -b "$TJ" "$BASE/studio/partners/$PID" | grep -oE 'class="reference">[^<]+' | sed 's/.*>//; s/ //g')
check "new code resets the password"       "303 $BASE/dashboard" "$(setup "$WORK/z.txt" 0993000001 "$NEW" teacher-pass-new)"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
