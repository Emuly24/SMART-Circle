#!/usr/bin/env bash
# End-to-end test of the free public library: notes the tutor shares (text and pictures only, never
# the exercises) as branded, printable handouts, and PDF handouts attached to lessons.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-library.sh
# Uses Form 2 Chemistry, student +265997700001 and notes/lessons titled "E2E Library ...".
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
upload() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" --form-string "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
sql "DELETE FROM users WHERE phone='+265997700001'"
sql "DELETE FROM notes WHERE title LIKE 'E2E Library %'; DELETE FROM lessons WHERE title LIKE 'E2E Library %'"
TOPIC=$(sql "SELECT id FROM topics WHERE form=2 AND subject='chemistry' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', 'Lib Student', '+265997700001', 'male', '$HASH')"
sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_confirmed_at) SELECT id, 2, 'general', NOW(), NOW() FROM users WHERE phone='+265997700001'"
TJ="$WORK/t.txt"; SJ="$WORK/s.txt"; GJ="$WORK/g.txt"
login "$TJ" 0999123456 correct-horse-1; login "$SJ" 0997700001 student-pass-1
php -r '$i = imagecreatetruecolor(320, 200); imagefill($i, 0, 0, imagecolorallocate($i, 31, 157, 107)); imagepng($i, $argv[1]);' "$WORK/pic.png"
printf '%%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%%%EOF\n' > "$WORK/h.pdf"
printf 'just some text' > "$WORK/fake.pdf"

echo "A shared note"
post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E Library Atoms" --data-urlencode form=2 --data-urlencode subject=chemistry --data-urlencode "topic_id=$TOPIC" --data-urlencode "summary=What everything is made of" >/dev/null
NOTE=$(sql "SELECT id FROM notes WHERE title='E2E Library Atoms'"); E="/studio/notes/$NOTE"
post "$TJ" "$E" "$E/text" --data-urlencode "body=$(printf '# Atoms\nEverything is made of **atoms**. Water is H_2O.\n\n## Mass\n= m = n * M\n\n> Real life: E2E-LIFE fertiliser bags list nitrogen.\n\n> Remember: E2E-KEY atoms are tiny.\n\n<script>alert(1)</script>')" >/dev/null
T=$(token "$TJ" "$BASE$E"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --form-string "_token=$T" -F "image=@$WORK/pic.png;type=image/png" --form-string "caption=E2E atom picture" "$BASE$E/image"
post "$TJ" "$E" "$E/checkpoint" >/dev/null
CPB=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND type='checkpoint'")
IMG=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND type='image'")
CP=$(sql "SELECT assessment_id FROM note_blocks WHERE id=$CPB")
post "$TJ" "/studio/questions/new?form=2&subject=chemistry" /studio/questions --data-urlencode form=2 --data-urlencode subject=chemistry --data-urlencode "topic_id=$TOPIC" --data-urlencode type=mcq --data-urlencode "prompt=E2E-LIB-QUESTION smallest particle?" --data-urlencode marks=1 --data-urlencode "options[]=Atom" --data-urlencode "options[]=Rock" --data-urlencode correct=0 >/dev/null
Q=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E-LIB-QUESTION%' ORDER BY id DESC LIMIT 1")
post "$TJ" "/studio/assessments/$CP" "/studio/assessments/$CP/questions" --data-urlencode "question_id=$Q" >/dev/null
post "$TJ" "$E" "$E/text" --data-urlencode "body=E2E-AFTER the second part." >/dev/null

check "editor offers the library"          "Show in the free library" "$(curl -s -b "$TJ" "$BASE$E")"
check "editor shows branded boxes"         "note-callout note-callout--key" "$(curl -s -b "$TJ" "$BASE$E")"
check "students cannot toggle (403)"       "403" "$(code -b "$SJ" -X POST "$BASE$E/library/on")"
check "bad action refused (404)"           "404" "$(post "$TJ" "$E" "$E/library/maybe" | cut -c1-3)"
check "add to library"                     "303 $BASE$E" "$(post "$TJ" "$E" "$E/library/on")"
check "  stored"                           "1" "$(sql "SELECT in_library FROM notes WHERE id=$NOTE")"
check "  editor says once published"       "once published" "$(curl -s -b "$TJ" "$BASE$E")"
check "draft not public yet (404)"         "404" "$(code "$BASE/library/notes/$NOTE")"
refute "draft not listed"                  "E2E Library Atoms" "$(curl -s "$BASE/library")"
sql "DELETE FROM login_attempts"
post "$TJ" "$E" "$E/publish" >/dev/null
check "published"                          "published" "$(sql "SELECT status FROM notes WHERE id=$NOTE")"

echo "Reading it with no account"
L=$(curl -s "$BASE/library")
check "library open to everyone"           "Free library" "$L"
check "  lists the note"                   "E2E Library Atoms" "$L"
check "  with its form"                    "Form 2" "$L"
check "  invites to apply"                 'href="/apply"' "$L"
check "guest menu has the library"         'href="/library"' "$(curl -s "$BASE/")"
check "home links the library"             'free library</a>' "$(curl -s "$BASE/")"
check "filter by subject keeps it"         "E2E Library Atoms" "$(curl -s "$BASE/library?form=2&subject=chemistry")"
refute "other subject hides it"            "E2E Library Atoms" "$(curl -s "$BASE/library?subject=physics")"
refute "other form hides it"               "E2E Library Atoms" "$(curl -s "$BASE/library?form=3")"
check "junk filters ignored"               "E2E Library Atoms" "$(curl -s "$BASE/library?form=x%27&subject=%3Cb%3E")"
P=$(curl -s "$BASE/library/notes/$NOTE")
check "handout opens (no login)"           "Everything is made of <strong>atoms</strong>" "$P"
check "  formatted (H₂O)"                  "H<sub>2</sub>O" "$P"
check "  equation in brand style"          'class="note-eq"' "$P"
check "  real-life box labelled"           '<span class="note-callout__label">Real life:</span> E2E-LIFE' "$P"
check "  remember box"                     'note-callout--key' "$P"
check "  typed HTML escaped"               "&lt;script&gt;" "$P"
check "  text after the exercise shown"    "E2E-AFTER" "$P"
check "  picture shown"                    "/library/notes/$NOTE/images/$IMG" "$P"
refute "  exercise NOT shown"              "E2E-LIB-QUESTION" "$P"
refute "  no answer form"                  "Submit and continue" "$P"
check "  print link"                       "/library/notes/$NOTE/print" "$P"
check "  invites to apply"                 "Want the full lessons?" "$P"
PR=$(curl -s "$BASE/library/notes/$NOTE/print")
check "print version (light A4)"           'data-theme-fixed' "$PR"
check "  print button"                     'id="print-btn"' "$PR"
check "  brand logo"                       "logo-horizontal" "$PR"
check "  text"                             "E2E-LIFE" "$PR"
refute "  no exercise"                     "E2E-LIB-QUESTION" "$PR"
check "picture opens (no login)"           "200" "$(code "$BASE/library/notes/$NOTE/images/$IMG")"
check "checkpoint is not a picture (404)"  "404" "$(code "$BASE/library/notes/$NOTE/images/$CPB")"
check "brand CSS: green heading bar"       ".note-prose h2::after" "$(curl -s "$BASE/assets/css/app.css")"
check "brand CSS: print colours kept"      "print-color-adjust: exact" "$(curl -s "$BASE/assets/css/app.css")"

echo "Taking it out"
post "$TJ" "$E" "$E/library/off" >/dev/null
check "out of the library (404)"           "404" "$(code "$BASE/library/notes/$NOTE")"
check "  picture gone too (404)"           "404" "$(code "$BASE/library/notes/$NOTE/images/$IMG")"
refute "  not listed"                      "E2E Library Atoms" "$(curl -s "$BASE/library")"
check "reader keeps the honesty rule"     "403" "$(code -b "$SJ" "$BASE/notes/$NOTE")"
post "$TJ" "$E" "$E/library/on" >/dev/null
post "$TJ" "$E" "$E/unpublish" >/dev/null
check "unpublished note leaves (404)"      "404" "$(code "$BASE/library/notes/$NOTE")"

echo "Lesson PDF handouts"
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
sql "INSERT INTO lessons (title, form, subject, topic_id, status, created_by, published_at, sha256) VALUES ('E2E Library Bonding', 2, 'chemistry', $TOPIC, 'published', $TID, NOW(), REPEAT('0', 64))"
LES=$(sql "SELECT id FROM lessons WHERE title='E2E Library Bonding'"); LE="/studio/lessons/$LES"
check "lesson page offers a handout"       "PDF handout" "$(curl -s -b "$TJ" "$BASE$LE")"
check "  says PDFs keep their own look"    "cannot restyle it" "$(curl -s -b "$TJ" "$BASE$LE")"
upload "$TJ" "$LE" "$LE/handout" -F "handout=@$WORK/fake.pdf;type=application/pdf" >/dev/null
check "non-PDF refused"                    "Upload a PDF file" "$(curl -s -b "$TJ" "$BASE$LE")"
check "students cannot upload (403)"       "403" "$(upload "$SJ" "/notes" "$LE/handout" -F "handout=@$WORK/h.pdf;type=application/pdf")"
check "upload PDF"                         "303" "$(upload "$TJ" "$LE" "$LE/handout" -F "handout=@$WORK/h.pdf;type=application/pdf")"
HP=$(sql "SELECT handout_path FROM lessons WHERE id=$LES")
check "  stored outside the web root"      "uploads/handouts/$LES-" "$HP"
check "  tutor can open it"                "%PDF" "$(curl -s -b "$TJ" "$BASE$LE/handout")"
check "not public yet (404)"               "404" "$(code "$BASE/library/lessons/$LES/handout")"
post "$TJ" "$LE" "$LE/handout/public" >/dev/null
check "made public"                        "1" "$(sql "SELECT handout_public FROM lessons WHERE id=$LES")"
check "  listed in the library"            "E2E Library Bonding" "$(curl -s "$BASE/library")"
check "  downloads with no login"          "%PDF" "$(curl -s "$BASE/library/lessons/$LES/handout")"
check "  served as PDF"                    "application/pdf" "$(curl -s -D - -o /dev/null "$BASE/library/lessons/$LES/handout")"
check "  friendly file name"               "SMART Circle - E2E Library Bonding.pdf" "$(curl -s -D - -o /dev/null "$BASE/library/lessons/$LES/handout")"
check "storage not web-reachable"          "404" "$(code "$BASE/storage/$HP")"
upload "$TJ" "$LE" "$LE/handout" -F "handout=@$WORK/h.pdf;type=application/pdf" >/dev/null
check "replacing keeps it public"          "1" "$(sql "SELECT handout_public FROM lessons WHERE id=$LES")"
if [[ -n "${STORAGE_DIR:-}" ]]; then check "  and removes the old file"  "1" "$(ls "$STORAGE_DIR/uploads/handouts" | grep -c "^$LES-")"; fi
sql "UPDATE lessons SET status='draft' WHERE id=$LES"
check "draft lesson's handout hidden"      "404" "$(code "$BASE/library/lessons/$LES/handout")"
sql "UPDATE lessons SET status='published' WHERE id=$LES"
post "$TJ" "$LE" "$LE/handout/private" >/dev/null
check "made private (404)"                 "404" "$(code "$BASE/library/lessons/$LES/handout")"
post "$TJ" "$LE" "$LE/handout/remove" --data-urlencode x=1 >/dev/null
check "removed"                            "NULL" "$($MYSQL -N -e "SELECT IFNULL(handout_path,'NULL') FROM lessons WHERE id=$LES")"
check "cannot go public with no file"      "0" "$(post "$TJ" "$LE" "$LE/handout/public" >/dev/null; sql "SELECT handout_public FROM lessons WHERE id=$LES")"

sql "DELETE FROM notes WHERE title LIKE 'E2E Library %'; DELETE FROM lessons WHERE title LIKE 'E2E Library %'; DELETE FROM questions WHERE prompt LIKE 'E2E-LIB-QUESTION%'"
sql "DELETE FROM users WHERE phone='+265997700001'"
echo; echo "Library: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
