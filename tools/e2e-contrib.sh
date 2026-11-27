#!/usr/bin/env bash
# End-to-end test of partner teachers' contributions: confirming the school calendar, sharing
# resources (upload or WhatsApp to the tutor) and suggesting missing topics, and the admin's review.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-contrib.sh
# Uses partner teachers +26598600000x at "E2E Contrib" schools.
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
sql "DELETE FROM topics WHERE title LIKE 'E2E contrib %'; DELETE FROM users WHERE phone LIKE '+26598600000%'; DELETE FROM schools WHERE name LIKE 'E2E Contrib%'"
HASH=$(php -r 'echo password_hash("partner-pass-1", PASSWORD_DEFAULT);')
for n in 1 2; do
    sql "INSERT INTO schools (name, district) VALUES ('E2E Contrib School $n', 'Ntcheu')"
    sql "INSERT INTO users (role, full_name, phone, password_hash) VALUES ('partner', 'Teacher Contrib $n', '+26598600000$n', '$HASH')"
    sql "INSERT INTO partner_profiles (user_id, school_id, subjects, verified_at) SELECT u.id, s.id, '$([[ $n == 1 ]] && echo 'physics,chemistry' || echo 'biology')', NOW() FROM users u, schools s WHERE u.phone='+26598600000$n' AND s.name='E2E Contrib School $n'"
done
SCH1=$(sql "SELECT id FROM schools WHERE name='E2E Contrib School 1'")
P1="$WORK/p1.txt"; P2="$WORK/p2.txt"; TJ="$WORK/t.txt"
login "$P1" 0986000001 partner-pass-1; login "$P2" 0986000002 partner-pass-1; login "$TJ" 0999123456 correct-horse-1

echo "The contribute page"
C=$(curl -s -b "$P1" "$BASE/partner/contribute")
check "opens for partner teachers"         "Help keep SMART Circle accurate" "$C"
check "  in the partner menu"              'href="/partner/contribute"' "$(curl -s -b "$P1" "$BASE/dashboard")"
check "  only their subjects to suggest"   ">Physics<" "$C"
refute "  not other subjects"              ">Biology<" "$C"
check "  WhatsApp button (number is set)"  "Send on WhatsApp instead" "$C"
check "students and visitors kept out"     "303" "$(code "$BASE/partner/contribute")"
check "  the admin uses the review page"   "403" "$(code -b "$TJ" "$BASE/partner/contribute")"

echo "Calendar check"
check "a problem needs a note"             "Tell the tutor what is wrong" "$(post "$P1" /partner/contribute /partner/contribute/calendar --data-urlencode answer=problem --data-urlencode note= >/dev/null; cat "$WORK/last.html")"
check "report a problem"                   "303" "$(post "$P1" /partner/contribute /partner/contribute/calendar --data-urlencode answer=problem --data-urlencode "note=E2E Our mid-term is 16-20 Feb, not 9-13 Feb" | cut -c1-3)"
check "  tutor notified"                   "Calendar problem reported: E2E Contrib School 1" "$(curl -s -b "$TJ" "$BASE/notifications")"
R=$(curl -s -b "$TJ" "$BASE/studio/contributions")
check "  on the review page"               "Our mid-term is 16-20 Feb" "$R"
check "  school 2 not checked yet"         "Not checked yet" "$R"
CID=$(sql "SELECT id FROM calendar_confirmations WHERE school_id=$SCH1 ORDER BY id DESC LIMIT 1")
check "admin marks it corrected"           "303" "$(post "$TJ" /studio/contributions "/studio/contributions/calendar/$CID/resolve" | cut -c1-3)"
check "  teacher sees it corrected"        "Problem corrected" "$(curl -s -b "$P1" "$BASE/partner/contribute")"
check "teacher confirms"                   "303" "$(post "$P1" /partner/contribute /partner/contribute/calendar --data-urlencode answer=correct | cut -c1-3)"
check "  shown as confirmed"               "Confirmed" "$(curl -s -b "$TJ" "$BASE/studio/contributions")"

echo "Sharing resources"
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > "$WORK/scheme.pdf"
T=$(token "$P1" "$BASE/partner/contribute")
R=$(curl -s -o /dev/null -w '%{http_code}' -b "$P1" -c "$P1" -F "_token=$T" -F kind=scheme_of_work -F "title=E2E Form 3 Chemistry scheme" -F subject=chemistry -F form=3 -F via=upload -F "file=@$WORK/scheme.pdf;type=application/pdf" "$BASE/partner/contribute/resource")
check "upload a scheme of work"            "303" "$R"
RID=$(sql "SELECT id FROM school_resources WHERE title='E2E Form 3 Chemistry scheme'")
check "  stored outside the web root"      "resources/$SCH1/$RID.pdf" "$(sql "SELECT file_path FROM school_resources WHERE id=$RID")"
check "  tutor notified"                   "New resource from E2E Contrib School 1" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  teacher downloads it"             "%PDF" "$(curl -s -b "$P1" "$BASE/partner/resources/$RID/file")"
check "  another school cannot"            "404" "$(code -b "$P2" "$BASE/partner/resources/$RID/file")"
check "  admin downloads it"               "attachment" "$(curl -s -D - -o /dev/null -b "$TJ" "$BASE/studio/resources/$RID/file")"
check "  visitors cannot"                  "303" "$(code "$BASE/studio/resources/$RID/file")"
echo "MZ not a document" > "$WORK/bad.exe"
T=$(token "$P1" "$BASE/partner/contribute")
check "  other file types refused"         "Upload a PDF, Word document or photo" "$(curl -s -b "$P1" -c "$P1" -F "_token=$T" -F kind=notes -F "title=E2E bad file" -F via=upload -F "file=@$WORK/bad.exe" "$BASE/partner/contribute/resource")"
check "  nothing stored for it"            "0" "$(sql "SELECT COUNT(*) FROM school_resources WHERE title='E2E bad file'")"
W=$(post "$P1" /partner/contribute /partner/contribute/resource --data-urlencode kind=past_paper --data-urlencode "title=E2E Form 4 Physics mock 2026" --data-urlencode subject=physics --data-urlencode form=4 --data-urlencode via=whatsapp)
check "send on WhatsApp opens WhatsApp"    "https://wa.me/265881193707?text=Hello%2C%20this%20is%20Teacher%20Contrib%201" "$W"
check "  with what is being sent"          "E2E%20Form%204%20Physics%20mock%202026%20%28Past%20paper%20or%20test%2C%20Form%204%20Physics%29" "$W"
check "  recorded as sent on WhatsApp"     "whatsapp" "$(sql "SELECT via FROM school_resources WHERE title='E2E Form 4 Physics mock 2026'")"
check "  name is required"                 "Give it a short name" "$(post "$P1" /partner/contribute /partner/contribute/resource --data-urlencode kind=notes --data-urlencode title= --data-urlencode via=whatsapp >/dev/null; cat "$WORK/last.html")"
R=$(curl -s -b "$TJ" "$BASE/studio/contributions")
check "admin sees both"                    "sent on WhatsApp" "$R"
check "admin marks seen"                   "303" "$(post "$TJ" /studio/contributions "/studio/contributions/resources/$RID/reviewed" | cut -c1-3)"
check "  teacher sees it was seen"         "seen by the tutor" "$(curl -s -b "$P1" "$BASE/partner/contribute")"

echo "Suggesting missing topics"
PARENT=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND parent_id IS NULL AND is_active=1 ORDER BY sort_order LIMIT 1")
check "suggest a subtopic"                 "303" "$(post "$P1" /partner/contribute /partner/contribute/suggest --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode parent_id=$PARENT --data-urlencode "title=E2E contrib Measuring with vernier callipers" --data-urlencode "note=Syllabus p. 12" | cut -c1-3)"
check "  outside their subjects refused"   "Choose one of your subjects" "$(post "$P1" /partner/contribute /partner/contribute/suggest --data-urlencode form=3 --data-urlencode subject=biology --data-urlencode "title=E2E contrib nope" >/dev/null; cat "$WORK/last.html")"
check "  wrong parent refused"             "Choose a main topic from the same form" "$(post "$P1" /partner/contribute /partner/contribute/suggest --data-urlencode form=2 --data-urlencode subject=physics --data-urlencode parent_id=$PARENT --data-urlencode "title=E2E contrib wrong parent" >/dev/null; cat "$WORK/last.html")"
check "  tutor notified"                   "Topic suggested by E2E Contrib School 1" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  not in the catalogue yet"         "0" "$(sql "SELECT COUNT(*) FROM topics WHERE title LIKE 'E2E contrib %'")"
SID=$(sql "SELECT id FROM topic_suggestions WHERE title='E2E contrib Measuring with vernier callipers'")
check "admin adds it (reworded)"           "303" "$(post "$TJ" /studio/contributions "/studio/contributions/suggestions/$SID" --data-urlencode decision=add --data-urlencode "title=E2E contrib Vernier callipers and micrometer" --data-urlencode "note=Thank you!" | cut -c1-3)"
check "  now a subtopic in the catalogue"  "$PARENT" "$(sql "SELECT parent_id FROM topics WHERE title='E2E contrib Vernier callipers and micrometer'")"
check "  suggestion linked to the topic"   "added" "$(sql "SELECT status FROM topic_suggestions WHERE id=$SID")"
check "  teacher told"                     "Your topic was added" "$(curl -s -b "$P1" "$BASE/notifications")"
check "  shown to the teacher"             "Added to the syllabus" "$(curl -s -b "$P1" "$BASE/partner/contribute")"
check "  cannot be decided twice"          "already been decided" "$(post "$TJ" /studio/contributions "/studio/contributions/suggestions/$SID" --data-urlencode decision=add >/dev/null; curl -s -b "$TJ" "$BASE/studio/contributions")"
post "$P2" /partner/contribute /partner/contribute/suggest --data-urlencode form=1 --data-urlencode subject=biology --data-urlencode "title=E2E contrib Already covered elsewhere" >/dev/null
SID2=$(sql "SELECT id FROM topic_suggestions WHERE title='E2E contrib Already covered elsewhere'")
check "admin declines with a note"         "303" "$(post "$TJ" /studio/contributions "/studio/contributions/suggestions/$SID2" --data-urlencode decision=decline --data-urlencode "note=This is part of Cells in Form 1" | cut -c1-3)"
check "  not added"                        "0" "$(sql "SELECT COUNT(*) FROM topics WHERE title='E2E contrib Already covered elsewhere'")"
check "  teacher sees why"                 "This is part of Cells in Form 1" "$(curl -s -b "$P2" "$BASE/partner/contribute")"
refute "  schools see only their own"      "vernier" "$(curl -s -b "$P2" "$BASE/partner/contribute")"
check "partners cannot decide"             "403" "$(post "$P1" /partner/contribute "/studio/contributions/suggestions/$SID2" --data-urlencode decision=add | cut -c1-3)"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
