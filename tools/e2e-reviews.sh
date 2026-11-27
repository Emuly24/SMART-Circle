#!/usr/bin/env bash
# End-to-end test of teachers checking notes: a partner teacher signs once (drawn or a photo), approves
# notes in their subjects or suggests changes; approval shows their name, school and signature on the
# student reader, the library handout and its printout, until the notes change.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-reviews.sh
# Uses Form 2 Physics, teachers +26599780000x, student +265997800009 and notes titled "E2E Review ...".
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
    sql "DELETE FROM notes WHERE title LIKE 'E2E Review %'; DELETE FROM questions WHERE prompt LIKE 'E2E-REV %'"
    sql "DELETE FROM users WHERE phone LIKE '+26599780000%'; DELETE FROM schools WHERE name LIKE 'E2E Review %'"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM notes WHERE title LIKE 'E2E Review %'; DELETE FROM users WHERE phone LIKE '+26599780000%'; DELETE FROM schools WHERE name LIKE 'E2E Review %'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Review Secondary', 'Mzimba')"
SCH=$(sql "SELECT id FROM schools WHERE name='E2E Review Secondary'")
TOPIC=$(sql "SELECT id FROM topics WHERE form=2 AND subject='physics' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($SCH, $TOPIC, 'covered', 'tutor')"
HASH=$(php -r 'echo password_hash("teacher-pass-1", PASSWORD_DEFAULT);')
teacher() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('partner', '$2', '$1', 'female', '$HASH')"
    sql "INSERT INTO partner_profiles (user_id, school_id, subjects, verified_at) SELECT id, $SCH, '$3', NOW() FROM users WHERE phone='$1'"
}
teacher +265997800001 "Grace Phiri" "physics,mathematics"
teacher +265997800002 "Bio Banda" "biology"
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', 'Rev Student', '+265997800009', 'male', '$HASH')"
sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 2, 'general', $SCH, NOW(), NOW() FROM users WHERE phone='+265997800009'"
TJ="$WORK/t.txt"; PJ="$WORK/p.txt"; BJ="$WORK/b.txt"; SJ="$WORK/s.txt"
login "$TJ" 0999123456 correct-horse-1; login "$PJ" 0997800001 teacher-pass-1; login "$BJ" 0997800002 teacher-pass-1; login "$SJ" 0997800009 teacher-pass-1
php -r '$i = imagecreatetruecolor(400, 150); imagefill($i, 0, 0, imagecolorallocate($i, 255, 255, 255)); imageline($i, 20, 100, 380, 40, imagecolorallocate($i, 20, 33, 61)); imagepng($i, $argv[1]);' "$WORK/sig.png"
printf 'not a picture' > "$WORK/fake.png"

echo "The tutor writes notes and asks for a check"
post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E Review Pressure" --data-urlencode form=2 --data-urlencode subject=physics --data-urlencode "topic_id=$TOPIC" >/dev/null
NOTE=$(sql "SELECT id FROM notes WHERE title='E2E Review Pressure'"); E="/studio/notes/$NOTE"
post "$TJ" "$E" "$E/text" --data-urlencode "body=$(printf '# Pressure\nE2E-REV-TEXT Pressure is force per area.\n= P = F / A')" >/dev/null
post "$TJ" "$E" "$E/checkpoint" >/dev/null
CP=$(sql "SELECT assessment_id FROM note_blocks WHERE note_id=$NOTE AND type='checkpoint'")
post "$TJ" "/studio/questions/new?form=2&subject=physics" /studio/questions --data-urlencode form=2 --data-urlencode subject=physics --data-urlencode "topic_id=$TOPIC" --data-urlencode type=mcq --data-urlencode "prompt=E2E-REV unit of pressure?" --data-urlencode marks=1 --data-urlencode "options[]=Pascal" --data-urlencode "options[]=Joule" --data-urlencode correct=0 >/dev/null
Q=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E-REV unit%'")
post "$TJ" "/studio/assessments/$CP" "/studio/assessments/$CP/questions" --data-urlencode "question_id=$Q" >/dev/null
post "$TJ" "$E" "$E/publish" >/dev/null
post "$TJ" "$E" "$E/library/on" >/dev/null
check "editor offers a check"              "Ask a teacher to check" "$(curl -s -b "$TJ" "$BASE$E")"
check "not in a teacher's list yet"        "Nothing to check right now" "$(curl -s -b "$PJ" "$BASE/partner/reviews")"
check "  and cannot be opened (404)"       "404" "$(code -b "$PJ" "$BASE/partner/reviews/$NOTE")"
check "students cannot ask (403)"          "403" "$(code -b "$SJ" -X POST "$BASE$E/review")"
check "ask for a check"                    "303 $BASE$E#reviews" "$(post "$TJ" "$E" "$E/review")"
check "  sent to the physics teachers"     "Sent to " "$(curl -s -b "$TJ" "$BASE$E")"
check "  editor says waiting"              "Waiting for a partner teacher" "$(curl -s -b "$TJ" "$BASE$E")"
check "teacher notified"                   "Please check: E2E Review Pressure" "$(curl -s -b "$PJ" "$BASE/notifications")"
refute "biology teacher not notified"      "E2E Review Pressure" "$(curl -s -b "$BJ" "$BASE/notifications")"

echo "The teacher checks"
check "menu has Check notes"               'href="/partner/reviews"' "$(curl -s -b "$PJ" "$BASE/dashboard")"
L=$(curl -s -b "$PJ" "$BASE/partner/reviews")
check "listed for the teacher"             "E2E Review Pressure" "$L"
check "  asked to sign first"              "add your signature" "$L"
refute "not for the biology teacher"       "E2E Review Pressure" "$(curl -s -b "$BJ" "$BASE/partner/reviews")"
check "  who cannot open it (404)"         "404" "$(code -b "$BJ" "$BASE/partner/reviews/$NOTE")"
check "students kept out (403)"            "403" "$(code -b "$SJ" "$BASE/partner/reviews")"
P=$(curl -s -b "$PJ" "$BASE/partner/reviews/$NOTE")
check "teacher reads the notes"            "E2E-REV-TEXT" "$P"
check "  sees the exercise"                "E2E-REV unit of pressure?" "$P"
check "  with its answer"                  "Answer: <strong>Pascal</strong>" "$P"
check "  approve needs a signature"        'value="approved" disabled' "$(echo "$P" | tr -s ' ')"
R=$(post "$PJ" "/partner/reviews/$NOTE" "/partner/reviews/$NOTE" --data-urlencode decision=approved)
check "approval refused without signature" "422" "$R"

echo "Signing"
T=$(token "$PJ" "$BASE/partner/signature")
check "signature needs agreement"          "422" "$(curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$PJ" -c "$PJ" --form-string "_token=$T" -F "photo=@$WORK/sig.png;type=image/png" "$BASE/partner/signature")"
check "  explains"                         "Please tick the box" "$(cat "$WORK/last.html")"
T=$(token "$PJ" "$BASE/partner/signature")
check "fake picture refused"               "422" "$(curl -s -o /dev/null -w '%{http_code}' -b "$PJ" -c "$PJ" --form-string "_token=$T" --form-string consent=1 -F "photo=@$WORK/fake.png;type=image/png" "$BASE/partner/signature")"
DRAWN="data:image/png;base64,$(base64 -w0 "$WORK/sig.png")"
T=$(token "$PJ" "$BASE/partner/signature")
check "drawn signature saved"              "303" "$(curl -s -o /dev/null -w '%{http_code}' -b "$PJ" -c "$PJ" --form-string "_token=$T" --form-string consent=1 --form-string "drawn=$DRAWN" --form-string "back=/partner/reviews/$NOTE" "$BASE/partner/signature")"
SIG=$(sql "SELECT signature_path FROM partner_profiles pp JOIN users u ON u.id=pp.user_id WHERE u.phone='+265997800001'")
check "  stored outside the web root"      "uploads/signatures/" "$SIG"
check "  teacher can see it"               "image/png" "$(curl -s -D - -o /dev/null -b "$PJ" "$BASE/partner/signature/image")"
check "  nobody else can (403)"            "403" "$(code -b "$SJ" "$BASE/partner/signature/image")"
check "storage not web-reachable"          "404" "$(code "$BASE/storage/$SIG")"
check "signature page safe for scripts"    "js/signature.js" "$(curl -s -b "$PJ" "$BASE/partner/signature")"

echo "Suggesting changes"
check "changes need a comment"             "422" "$(post "$PJ" "/partner/reviews/$NOTE" "/partner/reviews/$NOTE" --data-urlencode decision=changes | cut -c1-3)"
check "suggest changes"                    "303" "$(post "$PJ" "/partner/reviews/$NOTE" "/partner/reviews/$NOTE" --data-urlencode decision=changes --data-urlencode "comment=E2E-COMMENT Add the unit Pa after the formula." | cut -c1-3)"
check "tutor notified"                     "Changes suggested by Grace Phiri" "$(curl -s -b "$TJ" "$BASE/notifications")"
P=$(curl -s -b "$TJ" "$BASE$E")
check "editor shows the suggestion"        "E2E-COMMENT Add the unit Pa" "$P"
check "  and asks again"                   "Ask a teacher to check again" "$P"
refute "no badge yet for students"         "Checked by a qualified teacher" "$(curl -s -b "$SJ" "$BASE/notes/$NOTE")"
BLOCK=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND type='text' ORDER BY sort_order LIMIT 1")
post "$TJ" "$E" "$E/blocks/$BLOCK" --data-urlencode "body=$(printf '# Pressure\nE2E-REV-TEXT Pressure is force per area, in Pa.\n= P = F / A')" >/dev/null
post "$TJ" "$E" "$E/review" >/dev/null

echo "Approving"
check "approve with signature"             "303" "$(post "$PJ" "/partner/reviews/$NOTE" "/partner/reviews/$NOTE" --data-urlencode decision=approved | cut -c1-3)"
check "tutor notified"                     "Approved by Grace Phiri" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "every decision kept"                "changes approved" "$(sql "SELECT GROUP_CONCAT(decision ORDER BY id SEPARATOR ' ') FROM note_reviews WHERE note_id=$NOTE")"
check "moves to Approved"                  "✓ Approved" "$(curl -s -b "$PJ" "$BASE/partner/reviews")"
RID=$(sql "SELECT id FROM note_reviews WHERE note_id=$NOTE AND decision='approved'")
S=$(curl -s -b "$SJ" "$BASE/notes/$NOTE")
check "student sees the badge"             "Checked by a qualified teacher" "$S"
check "  with the teacher and school"      "Grace Phiri</strong>, E2E Review Secondary" "$S"
check "  and the signature"                "/reviews/$RID/signature" "$S"
L=$(curl -s "$BASE/library/notes/$NOTE")
check "library handout shows it"          "Grace Phiri" "$L"
check "print handout shows it"            "/reviews/$RID/signature" "$(curl -s "$BASE/library/notes/$NOTE/print")"
check "signature picture opens"           "image/png" "$(curl -s -D - -o /dev/null "$BASE/reviews/$RID/signature")"
check "editor shows checked"              "Checked by a qualified teacher:" "$(curl -s -b "$TJ" "$BASE$E")"

echo "A change needs a new check"
post "$TJ" "$E" "$E/blocks/$BLOCK" --data-urlencode "body=E2E-REV-TEXT changed after approval." >/dev/null
refute "badge gone for students"           "Checked by a qualified teacher" "$(curl -s -b "$SJ" "$BASE/notes/$NOTE")"
refute "badge gone from the library"       "Grace Phiri" "$(curl -s "$BASE/library/notes/$NOTE")"
check "old signature link closed (404)"    "404" "$(code "$BASE/reviews/$RID/signature")"
check "editor explains"                    "Changed since Grace Phiri approved it" "$(curl -s -b "$TJ" "$BASE$E")"
check "teacher asked to check again"       "Changed since it was approved" "$(curl -s -b "$PJ" "$BASE/partner/reviews")"
sql "UPDATE questions SET prompt='E2E-REV unit of pressure (SI)?' WHERE id=$Q"
post "$PJ" "/partner/reviews/$NOTE" "/partner/reviews/$NOTE" --data-urlencode decision=approved >/dev/null
check "approved again"                     "Checked by a qualified teacher" "$(curl -s -b "$SJ" "$BASE/notes/$NOTE")"
sql "UPDATE questions SET prompt='E2E-REV what is the unit of pressure?', updated_at = NOW() + INTERVAL 1 SECOND WHERE id=$Q"
refute "editing an exercise needs a check" "Checked by a qualified teacher" "$(curl -s -b "$SJ" "$BASE/notes/$NOTE")"

echo; echo "Reviews: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
