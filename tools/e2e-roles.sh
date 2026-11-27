#!/usr/bin/env bash
# End-to-end test of partner teachers' roles: Question Contributor, Guest Teacher (records a lesson,
# the tutor publishes it), teacher checks of lessons and handouts, and the certificate of contribution.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 STORAGE_DIR=... MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-roles.sh
# Uses Form 3 Physics, teachers +26599830000x, student +265998300009 and items titled "E2E Roles ...".
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="(_token|csrf-token)" (value|content)="[^"]+"' | head -1 | sed -E 's/.*(value|content)="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
field() { php -r '$d = json_decode(stream_get_contents(STDIN), true); echo is_array($d) && array_key_exists($argv[1], $d) ? $d[$argv[1]] : "";' "$1"; }
cleanup() {
    sql "DELETE FROM lessons WHERE title LIKE 'E2E Roles %'; DELETE FROM questions WHERE prompt LIKE 'E2E-ROLES %'; DELETE FROM question_suggestions WHERE prompt LIKE 'E2E-ROLES %'"
    sql "DELETE FROM issued_documents WHERE type='contribution_certificate' AND subject_id IN (SELECT id FROM users WHERE phone LIKE '+26599830000%')"
    sql "DELETE FROM users WHERE phone LIKE '+26599830000%'; DELETE FROM schools WHERE name LIKE 'E2E Roles %'"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM lessons WHERE title LIKE 'E2E Roles %'; DELETE FROM questions WHERE prompt LIKE 'E2E-ROLES %'; DELETE FROM question_suggestions WHERE prompt LIKE 'E2E-ROLES %'"
sql "DELETE FROM users WHERE phone LIKE '+26599830000%'; DELETE FROM schools WHERE name LIKE 'E2E Roles %'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Roles Secondary', 'Mangochi')"
SCH=$(sql "SELECT id FROM schools WHERE name='E2E Roles Secondary'")
TOPIC=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
BIOTOPIC=$(sql "SELECT id FROM topics WHERE form=3 AND subject='biology' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($SCH, $TOPIC, 'covered', 'tutor')"
HASH=$(php -r 'echo password_hash("roles-pass-1", PASSWORD_DEFAULT);')
teacher() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('partner', '$2', '$1', 'male', '$HASH')"
    sql "INSERT INTO partner_profiles (user_id, school_id, subjects, verified_at) SELECT id, $SCH, '$3', NOW() FROM users WHERE phone='$1'"
}
teacher +265998300001 "Kondwani Mbewe" "physics"
teacher +265998300002 "Other Teacher" "biology"
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', 'Roles Student', '+265998300009', 'female', '$HASH')"
sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 3, 'science', $SCH, NOW(), NOW() FROM users WHERE phone='+265998300009'"
P1=$(sql "SELECT id FROM users WHERE phone='+265998300001'"); TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
TJ="$WORK/t.txt"; PJ="$WORK/p.txt"; OJ="$WORK/o.txt"; SJ="$WORK/s.txt"
login "$TJ" 0999123456 correct-horse-1; login "$PJ" 0998300001 roles-pass-1; login "$OJ" 0998300002 roles-pass-1; login "$SJ" 0998300009 roles-pass-1

echo "Question Contributor"
check "menu has Suggest questions"         'href="/partner/questions"' "$(curl -s -b "$PJ" "$BASE/dashboard")"
check "page opens"                         "Suggest questions" "$(curl -s -b "$PJ" "$BASE/partner/questions")"
check "students kept out (403)"            "403" "$(code -b "$SJ" "$BASE/partner/questions")"
check "only their subjects"                "422" "$(post "$PJ" /partner/questions /partner/questions --data-urlencode form=3 --data-urlencode subject=biology --data-urlencode type=short_text --data-urlencode "prompt=E2E-ROLES wrong subject" --data-urlencode marks=2)"
check "  explained"                        "Choose one of the subjects you teach" "$(cat "$WORK/last.html")"
check "topic must match"                   "422" "$(post "$PJ" /partner/questions /partner/questions --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode "topic_id=$BIOTOPIC" --data-urlencode type=short_text --data-urlencode "prompt=E2E-ROLES wrong topic" --data-urlencode marks=2)"
check "suggest an MCQ"                     "303" "$(post "$PJ" /partner/questions /partner/questions --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode "topic_id=$TOPIC" --data-urlencode type=mcq --data-urlencode "prompt=E2E-ROLES What is the SI unit of force?" --data-urlencode marks=1 --data-urlencode "options[]=Newton" --data-urlencode "options[]=Joule" --data-urlencode "options[]=Watt" --data-urlencode correct=0)"
post "$PJ" /partner/questions /partner/questions --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode type=short_text --data-urlencode "prompt=E2E-ROLES Explain inertia." --data-urlencode marks=3 >/dev/null
check "  listed as waiting"                "Waiting for the tutor" "$(curl -s -b "$PJ" "$BASE/partner/questions")"
check "tutor notified"                     "Question suggested by Kondwani Mbewe" "$(curl -s -b "$TJ" "$BASE/notifications")"
P=$(curl -s -b "$TJ" "$BASE/studio/question-suggestions")
check "tutor sees it with the answer"      "Answer: <strong>Newton</strong>" "$P"
check "  and who suggested it"             "Kondwani Mbewe" "$P"
check "partners kept out (403)"            "403" "$(code -b "$PJ" "$BASE/studio/question-suggestions")"
S1=$(sql "SELECT id FROM question_suggestions WHERE prompt LIKE 'E2E-ROLES What%'"); S2=$(sql "SELECT id FROM question_suggestions WHERE prompt LIKE 'E2E-ROLES Explain%'")
post "$TJ" /studio/question-suggestions "/studio/question-suggestions/$S1" --data-urlencode decision=accept >/dev/null
check "accepted into the bank"             "mcq $P1" "$(sql "SELECT type, created_by FROM questions WHERE prompt LIKE 'E2E-ROLES What%'")"
check "  linked"                           "accepted" "$(sql "SELECT status FROM question_suggestions WHERE id=$S1")"
check "  cannot be decided twice"          "already been decided" "$(post "$TJ" /studio/question-suggestions "/studio/question-suggestions/$S1" --data-urlencode decision=accept >/dev/null; curl -s -b "$TJ" "$BASE/studio/question-suggestions")"
post "$TJ" /studio/question-suggestions "/studio/question-suggestions/$S2" --data-urlencode decision=decline --data-urlencode "note=" >/dev/null
check "decline needs a reason"             "pending" "$(sql "SELECT status FROM question_suggestions WHERE id=$S2")"
post "$TJ" /studio/question-suggestions "/studio/question-suggestions/$S2" --data-urlencode decision=decline --data-urlencode "note=We have this one already; try one about friction?" >/dev/null
P=$(curl -s -b "$PJ" "$BASE/partner/questions")
check "teacher sees it was added"          "In the question bank" "$P"
check "  and the reason for the other"     "try one about friction?" "$P"
check "  notified"                         "Your question was added to the question bank" "$(curl -s -b "$PJ" "$BASE/notifications")"

echo "Guest Teacher"
check "not a guest yet: no board (403)"    "403" "$(code -b "$PJ" "$BASE/studio/lab")"
check "  nor lessons (403)"                "403" "$(code -b "$PJ" "$BASE/studio/lessons")"
check "partner page offers it"             "Make them a guest teacher" "$(curl -s -b "$TJ" "$BASE/studio/partners/$P1")"
post "$TJ" "/studio/partners/$P1" "/studio/partners/$P1/guest" --data-urlencode guest=1 >/dev/null
check "made a guest teacher"               "1" "$(sql "SELECT guest_teacher FROM partner_profiles WHERE user_id=$P1")"
check "  told"                             "You can now record lessons as a guest teacher" "$(curl -s -b "$PJ" "$BASE/notifications")"
check "  dashboard: record a lesson"       'href="/studio/lab"' "$(curl -s -b "$PJ" "$BASE/dashboard")"
check "  board opens"                      "200" "$(code -b "$PJ" "$BASE/studio/lab")"
check "other partners still kept out"      "403" "$(code -b "$OJ" "$BASE/studio/lab")"
# Record (a tiny lesson file) and upload it as the guest teacher.
php -r '$h = json_encode(["version"=>1,"page"=>["width"=>1600,"height"=>1000],"title"=>"Guest","durationMs"=>5000,"events"=>[["t"=>0,"type"=>"stroke","id"=>"s1","tool"=>"pen","color"=>"#14213D","width"=>7,"points"=>[[10,10,0.5,0],[200,200,0.8,500]]]]]);
  file_put_contents($argv[1], "SCL1" . pack("V", strlen($h)) . $h . str_repeat("x", 5000));' "$WORK/g.scl"
SIZE=$(stat -c %s "$WORK/g.scl"); SHA=$(sha256sum "$WORK/g.scl" | cut -d' ' -f1)
CSRF=$(token "$PJ" "$BASE/studio/lab")
api() { curl -s -w '\n%{http_code}' -b "$PJ" -c "$PJ" -H "X-CSRF-Token: $CSRF" "$@"; }
R=$(api -H 'Content-Type: application/json' -d "{\"client_key\":\"e2e-roles-1\",\"title\":\"E2E Roles Guest Motion\",\"size\":$SIZE,\"sha256\":\"$SHA\"}" "$BASE/studio/uploads")
UP=$(head -1 <<<"$R" | field upload_id)
check "guest upload starts"                "200" "$(tail -1 <<<"$R")"
api -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/g.scl" "$BASE/studio/uploads/$UP/chunk?offset=0" >/dev/null
GL=$(api -X POST "$BASE/studio/uploads/$UP/finish" | head -1 | field lesson_id)
check "guest lesson created"               "draft $P1" "$(sql "SELECT status, created_by FROM lessons WHERE id=${GL:-0}")"
sql "UPDATE lessons SET title='E2E Roles Guest Motion' WHERE id=$GL"
sql "INSERT INTO lessons (title, form, subject, topic_id, status, created_by, published_at, sha256) VALUES ('E2E Roles Tutor Lesson', 3, 'physics', $TOPIC, 'published', $TID, NOW(), REPEAT('0', 64))"
TL=$(sql "SELECT id FROM lessons WHERE title='E2E Roles Tutor Lesson'")
L=$(curl -s -b "$PJ" "$BASE/studio/lessons")
check "guest sees their lesson"            "E2E Roles Guest Motion" "$L"
refute "  but not the tutor's"             "E2E Roles Tutor Lesson" "$L"
check "  cannot open the tutor's (403)"    "403" "$(code -b "$PJ" "$BASE/studio/lessons/$TL")"
check "only their subjects (403)"          "403" "$(post "$PJ" "/studio/lessons/$GL" "/studio/lessons/$GL" --data-urlencode "title=E2E Roles Guest Motion" --data-urlencode form=3 --data-urlencode subject=biology)"
FIELDS=(--data-urlencode "title=E2E Roles Guest Motion" --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode "topic_id=$TOPIC"
    --data-urlencode "summary=Speed and velocity" --data-urlencode "why_it_matters=Road safety" --data-urlencode "where_in_malawi=Minibuses on the M1"
    --data-urlencode "careers=Engineer" --data-urlencode "try_at_home=Time a walk")
check "button says send to the tutor"      "Save and send to the tutor" "$(curl -s -b "$PJ" "$BASE/studio/lessons/$GL")"
post "$PJ" "/studio/lessons/$GL" "/studio/lessons/$GL" "${FIELDS[@]}" --data-urlencode action=publish >/dev/null
check "guest cannot publish: sent instead" "draft 1" "$(sql "SELECT status, guest_submitted_at IS NOT NULL FROM lessons WHERE id=$GL")"
check "  tutor notified"                   "Guest lesson ready to publish: E2E Roles Guest Motion" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  tutor's list says so"             "Asks you to publish" "$(curl -s -b "$TJ" "$BASE/studio/lessons")"
check "  tutor sees who recorded it"       "Recorded by guest teacher" "$(curl -s -b "$TJ" "$BASE/studio/lessons/$GL")"
post "$TJ" "/studio/lessons/$GL" "/studio/lessons/$GL" "${FIELDS[@]}" --data-urlencode action=publish >/dev/null
check "tutor publishes"                    "published" "$(sql "SELECT status FROM lessons WHERE id=$GL")"
check "  guest thanked"                    "Your lesson is published: E2E Roles Guest Motion" "$(curl -s -b "$PJ" "$BASE/notifications")"
check "students see the guest teacher"     "Guest teacher: <strong>Kondwani Mbewe</strong>, E2E Roles Secondary" "$(curl -s -b "$SJ" "$BASE/lessons/$GL")"
check "guest cannot edit it now (403)"     "403" "$(post "$PJ" "/studio/lessons/$GL" "/studio/lessons/$GL" "${FIELDS[@]}")"
check "  nor delete it (403)"              "403" "$(post "$PJ" /studio/lessons "/studio/lessons/$GL/delete")"

echo "Teachers check lessons"
check "tutor can ask for a check"          "Ask a teacher to check" "$(curl -s -b "$TJ" "$BASE/studio/lessons/$GL")"
post "$TJ" "/studio/lessons/$GL" "/studio/lessons/$GL/review" >/dev/null
check "  sent"                             "Waiting for a partner teacher to watch" "$(curl -s -b "$TJ" "$BASE/studio/lessons/$GL")"
check "teacher notified"                   "Please check a lesson: E2E Roles Guest Motion" "$(curl -s -b "$PJ" "$BASE/notifications")"
check "listed for the teacher"             "/partner/reviews/lessons/$GL" "$(curl -s -b "$PJ" "$BASE/partner/reviews")"
check "not for the biology teacher (404)"  "404" "$(code -b "$OJ" "$BASE/partner/reviews/lessons/$GL")"
R=$(curl -s -b "$PJ" "$BASE/partner/reviews/lessons/$GL")
check "teacher page has the player"        "/partner/reviews/lessons/$GL/file" "$R"
check "  and the real-life sections"       "Minibuses on the M1" "$R"
check "recording plays for the teacher"    "200" "$(code -b "$PJ" "$BASE/partner/reviews/lessons/$GL/file")"
check "  not for others (404)"             "404" "$(code -b "$OJ" "$BASE/partner/reviews/lessons/$GL/file")"
check "approve needs a signature"          "422" "$(post "$PJ" "/partner/reviews/lessons/$GL" "/partner/reviews/lessons/$GL" --data-urlencode decision=approved)"
php -r '$i = imagecreatetruecolor(300, 100); imagefill($i, 0, 0, imagecolorallocate($i, 255, 255, 255)); imageline($i, 10, 80, 290, 20, 0); imagepng($i, $argv[1]);' "$WORK/sig.png"
T=$(token "$PJ" "$BASE/partner/signature")
curl -s -o /dev/null -b "$PJ" -c "$PJ" --form-string "_token=$T" --form-string consent=1 -F "photo=@$WORK/sig.png;type=image/png" "$BASE/partner/signature"
check "approve"                            "303" "$(post "$PJ" "/partner/reviews/lessons/$GL" "/partner/reviews/lessons/$GL" --data-urlencode decision=approved)"
RID=$(sql "SELECT id FROM lesson_reviews WHERE lesson_id=$GL AND decision='approved'")
S=$(curl -s -b "$SJ" "$BASE/lessons/$GL")
check "students see the check badge"       "Checked by a qualified teacher" "$S"
check "  with the signature"               "/reviews/lessons/$RID/signature" "$S"
check "signature opens"                    "image/png" "$(curl -s -D - -o /dev/null "$BASE/reviews/lessons/$RID/signature")"
post "$TJ" "/studio/lessons/$GL" "/studio/lessons/$GL" "${FIELDS[@]/Road safety/Road safety and sport}" >/dev/null
refute "a change hides the badge"          "Checked by a qualified teacher" "$(curl -s -b "$SJ" "$BASE/lessons/$GL")"
check "  and closes the signature (404)"   "404" "$(code "$BASE/reviews/lessons/$RID/signature")"
check "  tutor told why"                   "Changed since Kondwani Mbewe approved it" "$(curl -s -b "$TJ" "$BASE/studio/lessons/$GL")"

echo "Certificate of contribution"
check "partner page lists contribution"    "questions added to the question bank" "$(curl -s -b "$TJ" "$BASE/studio/partners/$P1")"
post "$TJ" "/studio/partners/$P1" "/studio/partners/$P1/certificate" >/dev/null
CODE=$(sql "SELECT code FROM issued_documents WHERE type='contribution_certificate' AND subject_id=$P1")
check "certificate issued"                 "L" "${CODE:0:1}"
check "teacher notified"                   "certificate of contribution is ready" "$(curl -s -b "$PJ" "$BASE/notifications")"
C=$(curl -s -b "$PJ" "$BASE/letters/$CODE")
check "teacher opens it"                   "Certificate of contribution" "$C"
check "  lists the lesson taught"          "<strong>1</strong> lessons taught as a guest teacher" "$C"
check "  and the question added"           "<strong>1</strong> questions added to the question bank" "$C"
check "  verifiable"                       "/verify/$CODE" "$C"
check "others cannot open it (404)"        "404" "$(code -b "$OJ" "$BASE/letters/$CODE")"
check "nothing to certify: refused"        "nothing to certify" "$(post "$TJ" "/studio/partners/$(sql "SELECT id FROM users WHERE phone='+265998300002'")" "/studio/partners/$(sql "SELECT id FROM users WHERE phone='+265998300002'")/certificate" >/dev/null; curl -s -b "$TJ" "$BASE/studio/partners/$(sql "SELECT id FROM users WHERE phone='+265998300002'")")"

echo "Stopping guest teaching"
post "$TJ" "/studio/partners/$P1" "/studio/partners/$P1/guest" --data-urlencode guest=0 >/dev/null
check "board closed again (403)"           "403" "$(code -b "$PJ" "$BASE/studio/lab")"
check "their lesson stays"                 "published" "$(sql "SELECT status FROM lessons WHERE id=$GL")"

echo; echo "Roles: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
