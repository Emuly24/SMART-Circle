#!/usr/bin/env bash
# End-to-end test of admissions: apply -> entry quiz -> status -> tutor review -> letters ->
# student account setup -> onboarding, plus seat limits and spam protection.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) whose secrets set
# admissions.min_seconds_to_fill = 0 and admissions.max_per_ip_per_day = 100, and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-admissions.sh
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1"; }

# A small real JPEG as the school report.
php -r '$i = imagecreatetruecolor(40, 30); imagejpeg($i, $argv[1]);' "$WORK/report.jpg"

# apply JAR name gender form phone subjects(csv) help(csv) [extra curl args...]
apply() {
    local jar=$1 name=$2 gender=$3 form=$4 phone=$5 taken=$6 help=$7; shift 7
    local T args=()
    T=$(token "$jar" "$BASE/apply")
    IFS=, read -ra TK <<<"$taken"; for s in "${TK[@]}"; do args+=(--form-string "subjects_taken[]=$s"); done
    IFS=, read -ra HP <<<"$help"; for s in "${HP[@]}"; do args+=(--form-string "help_subjects[]=$s"); done
    curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" \
        --form-string "_token=$T" --form-string "full_name=$name" --form-string "gender=$gender" --form-string "form=$form" \
        --form-string "school_name=Likuni Girls Secondary" --form-string "district=Lilongwe" --form-string "phone=$phone" \
        --form-string "motivation=I want to understand science deeply and use it to help my community grow." \
        --form-string "device=own_phone" --form-string "data_access=weekly" --form-string "guardian_name=Mary Phiri" \
        --form-string "guardian_phone=0888111222" --form-string "guardian_relationship=Parent" --form-string "guardian_consent=1" \
        --form-string "truthful=1" "${args[@]}" "$@" "$BASE/apply"
}
ref_of() { sql "SELECT reference FROM applications WHERE phone='$1'"; }
id_of() { sql "SELECT id FROM applications WHERE phone='$1'"; }

sql "DELETE FROM login_attempts"

echo "Public pages"
check "home shows Apply button"           "Apply now" "$(curl -s "$BASE/")"
check "home shows the seat chart"         "24 of 24 seats open" "$(curl -s "$BASE/")"
check "apply form loads"                  "Apply to SMART Circle" "$(curl -s "$BASE/apply")"

echo "Spam protection and validation"
A="$WORK/a.txt"
check "honeypot field blocks bots"        "take a moment" "$(apply "$A" "Bot Bot" female 3 0991000001 mathematics mathematics --form-string website=http://spam >/dev/null; cat "$WORK/last.html")"
check "missing CSRF refused"              "403" "$(curl -s -o /dev/null -w '%{http_code}' --form-string full_name=x "$BASE/apply")"
R=$(apply "$A" "Chikondi" female 3 12345 "" "")
check "invalid form -> 422"               "422" "$R"
check "  shows field errors"              "Enter your first name and surname" "$(cat "$WORK/last.html")"
check "  keeps typed answers"             'value="Chikondi"' "$(cat "$WORK/last.html")"

echo "Apply + entry quiz (science track)"
R=$(apply "$A" "Chikondi Banda" female 3 0991000002 mathematics,english,biology,physics,chemistry physics,chemistry -F "report=@$WORK/report.jpg;type=image/jpeg")
check "valid application -> quiz"          "303 $BASE/apply/quiz" "$R"
REF=$(ref_of +265991000002)
check "reference format"                   "SC-" "$REF"
check "report stored"                      "image/jpeg" "$(sql "SELECT report_mime FROM applications WHERE reference='$REF'")"
check "science track saved"                "science" "$(sql "SELECT track FROM applications WHERE reference='$REF'")"
Q=$(curl -s -b "$A" -c "$A" "$BASE/apply/quiz")
check "quiz has 10 questions"              "10" "$(grep -c 'class="card quiz-q"' <<<"$Q")"
check "quiz never reveals answers"         "0" "$(grep -ciE 'explanation|data-correct|name="correct"|Moving them back' <<<"$Q" || true)"
T=$(token "$A" "$BASE/apply/quiz")
ANS=(); for qid in $(grep -oE 'name="q[0-9]+"' <<<"$Q" | sort -u | grep -oE '[0-9]+'); do ANS+=(--data-urlencode "q$qid=b"); done
check "quiz submit -> done"                "303 $BASE/apply/done" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$A" -c "$A" --data-urlencode "_token=$T" "${ANS[@]}" "$BASE/apply/quiz")"
check "done page shows reference"          "$REF" "$(curl -s -b "$A" "$BASE/apply/done")"
check "quiz graded (all-B answers)"        "/10" "$(sql "SELECT CONCAT(quiz_score, '/', quiz_total) FROM applications WHERE reference='$REF'")"
check "quiz cannot be retaken"             "303 $BASE/apply/done" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$A" "$BASE/apply/quiz")"
check "same phone cannot apply twice"      "already an application" "$(apply "$WORK/b.txt" "Chikondi Banda" female 3 0991000002 mathematics mathematics >/dev/null; cat "$WORK/last.html")"

echo "Humanities track"
H="$WORK/h.txt"
apply "$H" "Thoko Mwale" male 4 0991000003 mathematics,english,biology,physics physics,biology >/dev/null
check "humanities track saved"             "humanities" "$(sql "SELECT track FROM applications WHERE phone='+265991000003'")"
check "physics help dropped"               "biology" "$(sql "SELECT help_subjects FROM applications WHERE phone='+265991000003'")"
check "humanities quiz has 6 questions"    "6" "$(curl -s -b "$H" "$BASE/apply/quiz" | grep -c 'class="card quiz-q"')"

echo "Status check"
S="$WORK/s.txt"
T=$(token "$S" "$BASE/apply/status")
check "wrong phone -> not found"           "404" "$(curl -s -o /dev/null -w '%{http_code}' -b "$S" -c "$S" --data-urlencode "_token=$T" --data-urlencode "reference=$REF" --data-urlencode "phone=0991999999" "$BASE/apply/status")"
T=$(token "$S" "$BASE/apply/status")
check "lower-case ref + spaced phone work" "Received" "$(curl -s -b "$S" -c "$S" --data-urlencode "_token=$T" --data-urlencode "reference=$(tr 'A-Z-' 'a-z ' <<<"$REF")" --data-urlencode "phone=099 100 0002" "$BASE/apply/status")"

echo "Tutor review"
TJ="$WORK/t.txt"
T=$(token "$TJ" "$BASE/login"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "phone=0999123456" --data-urlencode "password=correct-horse-1" "$BASE/login"
ID=$(id_of +265991000002)
check "admissions list shows applicant"    "Chikondi Banda" "$(curl -s -b "$TJ" "$BASE/studio/admissions")"
check "detail page shows quiz review"      "Entry quiz" "$(curl -s -b "$TJ" "$BASE/studio/admissions/$ID")"
check "tutor can open the report"          "image/jpeg" "$(curl -s -o /dev/null -w '%{content_type}' -b "$TJ" "$BASE/studio/admissions/$ID/report")"
check "guest cannot open the report"       "303" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/studio/admissions/$ID/report")"
T=$(token "$TJ" "$BASE/studio/admissions/$ID")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "decision=admit" --data-urlencode "note=Strong motivation" "$BASE/studio/admissions/$ID/decision"
PAGE=$(curl -s -b "$TJ" "$BASE/studio/admissions/$ID")
CODE=$(grep -oE 'class="reference">[A-Z0-9 ]+<' <<<"$PAGE" | sed -E 's/.*>([A-Z0-9 ]+)</\1/' | tr -d ' ')
check "admit shows an 8-char setup code"   "8" "${#CODE}"
check "WhatsApp link to the student"       "wa.me/265991000002" "$PAGE"
check "student account created"            "student" "$(sql "SELECT role FROM users WHERE phone='+265991000002'")"
check "profile has the track"              "science" "$(sql "SELECT track FROM student_profiles sp JOIN users u ON u.id=sp.user_id WHERE u.phone='+265991000002'")"
LETTER=$(sql "SELECT code FROM issued_documents WHERE subject_type='application' AND subject_id=$ID AND type='admission_letter'")
check "admission letter issued"            "L" "$LETTER"
LTXT=$(curl -s -b "$TJ" "$BASE/letters/$LETTER")
check "letter renders for tutor"           "Congratulations" "$LTXT"
check "  letter names the student"         "Dear Chikondi," "$LTXT"
check "  letter shows reference + school"  "Ref: $REF" "$LTXT"
check "  letter names the guardian"        "Parent/guardian: Mary Phiri" "$LTXT"
check "letter hidden from strangers"       "404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/letters/$LETTER")"
check "verify page: genuine"               "Genuine" "$(curl -s "$BASE/verify/$(tr 'A-Z' 'a-z' <<<"$LETTER")")"
check "verify unknown code: not found"     "404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/verify/LZZZZZZZ")"
check "applicant sees letter link"         "/letters/$LETTER" "$(T=$(token "$S" "$BASE/apply/status"); curl -s -b "$S" -c "$S" --data-urlencode "_token=$T" --data-urlencode "reference=$REF" --data-urlencode "phone=0991000002" "$BASE/apply/status")"
check "applicant can open own letter"      "200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$S" "$BASE/letters/$LETTER")"
check "home seat chart updated"            "23 of 24 seats open" "$(curl -s "$BASE/")"

echo "Student account setup"
W="$WORK/w.txt"
T=$(token "$W" "$BASE/welcome")
check "wrong code refused"                 "do not match" "$(curl -s -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "phone=0991000002" --data-urlencode "code=WRONG234" "$BASE/welcome")"
T=$(token "$W" "$BASE/welcome")
check "right code (lower case, spaced)"    "303 $BASE/welcome/password" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "phone=+265 991 000 002" --data-urlencode "code=$(tr 'A-Z' 'a-z' <<<"${CODE:0:4} ${CODE:4}")" "$BASE/welcome")"
T=$(token "$W" "$BASE/welcome/password")
check "short password refused"             "at least 8" "$(curl -s -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "password=short" --data-urlencode "password_confirm=short" "$BASE/welcome/password")"
T=$(token "$W" "$BASE/welcome/password")
check "password set -> onboarding"         "303 $BASE/welcome/onboarding" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "password=my-new-pass-1" --data-urlencode "password_confirm=my-new-pass-1" "$BASE/welcome/password")"
check "dashboard waits for onboarding"     "303 $BASE/welcome/onboarding" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$W" "$BASE/dashboard")"
check "lessons wait for onboarding"        "303 $BASE/welcome/onboarding" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$W" "$BASE/lessons")"
T=$(token "$W" "$BASE/welcome/onboarding")
check "both boxes required"                "tick both boxes" "$(curl -s -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "agreement=1" "$BASE/welcome/onboarding")"
T=$(token "$W" "$BASE/welcome/onboarding")
curl -s -o /dev/null -b "$W" -c "$W" --data-urlencode "_token=$T" --data-urlencode "agreement=1" --data-urlencode "guardian=1" "$BASE/welcome/onboarding"
check "student dashboard after onboarding" "Hello, Chikondi" "$(curl -s -b "$W" "$BASE/dashboard")"
check "code cannot be reused"              "do not match" "$(W2="$WORK/w2.txt"; T=$(token "$W2" "$BASE/welcome"); curl -s -b "$W2" -c "$W2" --data-urlencode "_token=$T" --data-urlencode "phone=0991000002" --data-urlencode "code=$CODE" "$BASE/welcome")"
L2="$WORK/l2.txt"; T=$(token "$L2" "$BASE/login")
check "normal login with new password"     "303 $BASE/dashboard" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$L2" -c "$L2" --data-urlencode "_token=$T" --data-urlencode "phone=0991000002" --data-urlencode "password=my-new-pass-1" "$BASE/login")"
check "student cannot open admissions"     "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$L2" "$BASE/studio/admissions")"

echo "Seat limits, waiting list, changed decisions"
for n in 4 5 6; do apply "$WORK/x$n.txt" "Girl Number$n" female 3 099100000$n mathematics mathematics >/dev/null; done
for n in 4 5; do I=$(id_of +26599100000$n); T=$(token "$TJ" "$BASE/studio/admissions/$I"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "decision=admit" "$BASE/studio/admissions/$I/decision"; done
I6=$(id_of +265991000006)
check "4th Form 3 girl: admit disabled"    "disabled>Admit" "$(curl -s -b "$TJ" "$BASE/studio/admissions/$I6" | tr -d '\n' | grep -oE 'disabled>Admit' || true)"
T=$(token "$TJ" "$BASE/studio/admissions/$I6"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "decision=admit" "$BASE/studio/admissions/$I6/decision"
check "  ...and the server refuses it"     "seats for girls are taken" "$(curl -s -b "$TJ" "$BASE/studio/admissions/$I6")"
check "  girl not admitted"                "submitted" "$(sql "SELECT status FROM applications WHERE id=$I6")"
check "boys' seats unaffected"             "3 open" "$(curl -s -b "$TJ" "$BASE/studio/admissions" | tr -d '\n' | grep -oE 'Form 3</span>.*' | sed -E 's/Form 4.*//' | grep -oE '[0-9] open|Full' | tr '\n' ' ')"
T=$(token "$TJ" "$BASE/studio/admissions/$I6"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "decision=waitlist" "$BASE/studio/admissions/$I6/decision"
WL=$(sql "SELECT code FROM issued_documents WHERE subject_id=$I6 AND type='waitlist_letter'")
check "waiting list letter issued"         "L" "$WL"
T=$(token "$TJ" "$BASE/studio/admissions/$I6"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "decision=decline" "$BASE/studio/admissions/$I6/decision"
check "old letter shows as replaced"       "Replaced" "$(curl -s "$BASE/verify/$WL")"
check "decline letter issued"              "decline_letter" "$(sql "SELECT type FROM issued_documents WHERE subject_id=$I6 AND revoked_at IS NULL")"

echo "Intake open/close"
T=$(token "$TJ" "$BASE/studio/admissions/intake")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "name=First intake" --data-urlencode "seats_per_group=3" "$BASE/studio/admissions/intake"
check "closed intake: apply page says so"  "Applications are closed" "$(curl -s "$BASE/apply")"
check "closed intake: home has no Apply"   "Applications open soon" "$(curl -s "$BASE/")"
T=$(token "$TJ" "$BASE/studio/admissions/intake")
curl -s -o /dev/null -b "$TJ" -c "$TJ" --data-urlencode "_token=$T" --data-urlencode "name=First intake" --data-urlencode "seats_per_group=3" --data-urlencode "is_open=1" "$BASE/studio/admissions/intake"
check "reopened intake accepts again"      "Apply to SMART Circle" "$(curl -s "$BASE/apply")"

echo
echo "Passed: $PASS  Failed: $FAIL"
exit $(( FAIL > 0 ))
