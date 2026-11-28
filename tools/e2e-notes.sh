#!/usr/bin/env bash
# End-to-end test of notes and slides with checkpoint exercises (locked reading), encouraging
# marking (Got it / Partly there / Not yet with three feedback notes), the marking queue,
# students' latest feedback, and re-check requests.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-notes.sh
# Uses Form 1 Biology, students +26599700000x and schools named "E2E Notes ...".
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
sql "DELETE FROM users WHERE phone LIKE '+26599700000%'"
sql "DELETE FROM notes WHERE title LIKE 'E2E %'; DELETE FROM questions WHERE prompt LIKE 'E2E notes %'; DELETE FROM schools WHERE name LIKE 'E2E Notes%'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Notes Hill', 'Zomba'), ('E2E Notes Vale', 'Dedza')"
HILL=$(sql "SELECT id FROM schools WHERE name='E2E Notes Hill'"); VALE=$(sql "SELECT id FROM schools WHERE name='E2E Notes Vale'")
TOPIC=$(sql "SELECT id FROM topics WHERE form=1 AND subject='biology' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($HILL, $TOPIC, 'covered', 'tutor')"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
student() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', '$2', '$1', 'female', '$HASH')"
    sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 1, 'general', $3, NOW(), NOW() FROM users WHERE phone='$1'"
}
student +265997000001 "Rose Hill" "$HILL"
student +265997000002 "Ruth Vale" "$VALE"
R1="$WORK/r1.txt"; R2="$WORK/r2.txt"; TJ="$WORK/t.txt"
login "$R1" 0997000001 student-pass-1; login "$R2" 0997000002 student-pass-1; login "$TJ" 0999123456 correct-horse-1
R1ID=$(sql "SELECT id FROM users WHERE phone='+265997000001'")
php -r '$i = imagecreatetruecolor(320, 200); imagefill($i, 0, 0, imagecolorallocate($i, 29, 78, 137)); imagepng($i, $argv[1]);' "$WORK/slide.png"
printf 'not a picture' > "$WORK/fake.png"

echo "Writing notes"
check "notes page loads"                   "Notes &amp; slides" "$(curl -s -b "$TJ" "$BASE/studio/notes")"
check "students kept out of studio"        "403" "$(code -b "$R1" "$BASE/studio/notes")"
check "topic required"                     "422" "$(post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E Cells" --data-urlencode form=1 --data-urlencode subject=biology)"
R=$(post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E Cells" --data-urlencode form=1 --data-urlencode subject=biology --data-urlencode "topic_id=$TOPIC")
check "create -> editor"                   "303 $BASE/studio/notes/" "$R"
NOTE=$(sql "SELECT id FROM notes WHERE title='E2E Cells'")
E="/studio/notes/$NOTE"
post "$TJ" "$E" "$E/text" --data-urlencode "body=$(printf '# Cells\nAll living things are made of cells. Water is H_2O.\n\n> Real life: doctors look at blood cells to diagnose malaria.\n\n<script>alert(1)</script>')" >/dev/null
T=$(token "$TJ" "$BASE$E"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --form-string "_token=$T" -F "image=@$WORK/slide.png;type=image/png" --form-string "caption=E2E slide one" "$BASE$E/image"
T=$(token "$TJ" "$BASE$E"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --form-string "_token=$T" -F "image=@$WORK/fake.png;type=image/png" "$BASE$E/image"
check "fake picture refused"               "Use a JPG, PNG or WebP" "$(curl -s -b "$TJ" "$BASE$E")"
R=$(post "$TJ" "$E" "$E/checkpoint")
check "checkpoint -> question picker"      "303 $BASE/studio/assessments/" "$R"
CP1=$(sql "SELECT assessment_id FROM note_blocks WHERE note_id=$NOTE AND type='checkpoint' ORDER BY sort_order LIMIT 1")
check "  is a checkpoint exercise"         "note_check" "$(sql "SELECT kind FROM assessments WHERE id=$CP1")"
check "  builder links back to notes"      "Back to the notes" "$(curl -s -b "$TJ" "$BASE/studio/assessments/$CP1")"
post "$TJ" "$E" "$E/text" --data-urlencode "body=$(printf '## Part two\nE2E-SECRET-PART-TWO The nucleus controls the cell.')" >/dev/null
T=$(token "$TJ" "$BASE$E"); curl -s -o /dev/null -b "$TJ" -c "$TJ" --form-string "_token=$T" -F "image=@$WORK/slide.png;type=image/png" --form-string "caption=E2E slide two" "$BASE$E/image"
post "$TJ" "$E" "$E/checkpoint" --data-urlencode final=1 >/dev/null
CP2=$(sql "SELECT assessment_id FROM note_blocks WHERE note_id=$NOTE AND type='checkpoint' AND is_final=1")
IMG1=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND type='image' ORDER BY sort_order LIMIT 1")
IMG2=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND type='image' ORDER BY sort_order LIMIT 1 OFFSET 1")
B1=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND assessment_id=$CP1"); B2=$(sql "SELECT id FROM note_blocks WHERE note_id=$NOTE AND assessment_id=$CP2")
check "six parts in order"                 "text image checkpoint text image checkpoint" "$(sql "SELECT GROUP_CONCAT(type ORDER BY sort_order SEPARATOR ' ') FROM note_blocks WHERE note_id=$NOTE")"
check "publish refused: empty checkpoint"  "Every checkpoint needs at least one question" "$(post "$TJ" "$E" "$E/publish" >/dev/null; curl -s -b "$TJ" "$BASE$E")"
newq() { post "$TJ" "/studio/questions/new?form=1&subject=biology" /studio/questions --data-urlencode form=1 --data-urlencode subject=biology --data-urlencode "topic_id=$TOPIC" "$@" >/dev/null; }
newq --data-urlencode type=mcq --data-urlencode "prompt=E2E notes Which part controls the cell?" --data-urlencode marks=1 --data-urlencode "options[]=Wall" --data-urlencode "options[]=Nucleus" --data-urlencode correct=1
newq --data-urlencode type=short_text --data-urlencode "prompt=E2E notes Why do doctors look at blood cells?" --data-urlencode marks=4
newq --data-urlencode type=mcq --data-urlencode "prompt=E2E notes What is water?" --data-urlencode marks=2 --data-urlencode "options[]=H2O" --data-urlencode "options[]=CO2" --data-urlencode correct=0
QM=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E notes Which%'"); QS=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E notes Why%'"); QW=$(sql "SELECT id FROM questions WHERE prompt LIKE 'E2E notes What%'")
for q in $QM $QS; do post "$TJ" "/studio/assessments/$CP1" "/studio/assessments/$CP1/questions" --data-urlencode "question_id=$q" >/dev/null; done
post "$TJ" "/studio/assessments/$CP2" "/studio/assessments/$CP2/questions" --data-urlencode "question_id=$QW" >/dev/null
post "$TJ" "$E" "$E/publish" >/dev/null
check "published with its checkpoints"     "published published published" "$(sql "SELECT n.status, (SELECT GROUP_CONCAT(status SEPARATOR ' ') FROM assessments WHERE note_id=n.id) FROM notes n WHERE n.id=$NOTE")"
check "taught student notified"            "New notes: E2E Cells" "$(curl -s -b "$R1" "$BASE/notifications")"
refute "untaught student not notified"     "E2E Cells" "$(curl -s -b "$R2" "$BASE/notifications")"
refute "checkpoints not listed with exams" "E2E Cells" "$(curl -s -b "$R1" "$BASE/assessments")"

echo "Reading up to the checkpoint"
check "notes list shows progress"          "0 of 2 exercises done" "$(curl -s -b "$R1" "$BASE/notes")"
P=$(curl -s -b "$R1" "$BASE/notes/$NOTE")
check "first part shown"                   "All living things are made of cells" "$P"
check "  formatted (H₂O, real-life box)"   "H<sub>2</sub>O" "$P"
check "  real-life box"                    "note-callout" "$P"
check "  typed HTML escaped"               "&lt;script&gt;" "$P"
check "checkpoint form shown"              "Submit and continue" "$P"
refute "part two NOT sent to the phone"    "E2E-SECRET-PART-TWO" "$P"
check "  but they know more is coming"     "will open after this exercise" "$P"
refute "  answers never sent"              'name="correct"' "$P"
check "first slide opens"                  "200" "$(code -b "$R1" "$BASE/notes/$NOTE/images/$IMG1")"
check "slide after the checkpoint locked"  "404" "$(code -b "$R1" "$BASE/notes/$NOTE/images/$IMG2")"
check "skipping to the final is refused"   "Finish the exercise you are on first" "$(post "$R1" "/notes/$NOTE" "/notes/$NOTE/checkpoint/$B2" --data-urlencode "answer[$QW]=0" >/dev/null; curl -s -b "$R1" "$BASE/notes/$NOTE")"
R=$(post "$R1" "/notes/$NOTE" "/notes/$NOTE/checkpoint/$B1" --data-urlencode "answer[$QM]=1" --data-urlencode "answer[$QS]=They count the cells to see if malaria is there.")
check "submit checkpoint"                  "303 $BASE/notes/$NOTE#block-$B1" "$R"
P=$(curl -s -b "$R1" "$BASE/notes/$NOTE")
check "part two now open"                  "E2E-SECRET-PART-TWO" "$P"
check "  checkpoint marked done"           "Checkpoint done" "$P"
check "  waiting for the tutor's marks"    "The tutor will mark your written answers" "$P"
check "second slide now opens"             "200" "$(code -b "$R1" "$BASE/notes/$NOTE/images/$IMG2")"
check "tutor told there is marking"        "To mark: Checkpoint 1" "$(curl -s -b "$TJ" "$BASE/notifications")"
post "$R1" "/notes/$NOTE" "/notes/$NOTE/checkpoint/$B2" --data-urlencode "answer[$QW]=0" >/dev/null
P=$(curl -s -b "$R1" "$BASE/notes/$NOTE")
check "final exercise marked at once"      "Score: <strong>2 / 2" "$P"
check "  notes finished"                   "You have finished these notes" "$P"

echo "Honesty rule"
check "untaught school: locked (403)"      "403" "$(code -b "$R2" "$BASE/notes/$NOTE")"
check "  explains why"                     "These notes open when your school has taught" "$(curl -s -b "$R2" "$BASE/notes/$NOTE")"
check "  slides locked too"                "404" "$(code -b "$R2" "$BASE/notes/$NOTE/images/$IMG1")"

echo "Encouraging marking"
SUB=$(sql "SELECT id FROM submissions WHERE assessment_id=$CP1 AND user_id=$R1ID")
check "marking queue lists it"             "Rose Hill" "$(curl -s -b "$TJ" "$BASE/studio/marking")"
R=$(post "$TJ" "/studio/submissions/$SUB" "/studio/submissions/$SUB" --data-urlencode "score[$QS]=2" \
    --data-urlencode "feedback[did_well][$QS]=You linked blood cells to malaria." \
    --data-urlencode "feedback[off_track][$QS]=Doctors look for the parasite inside red blood cells, not count them." \
    --data-urlencode "feedback[next_step][$QS]=Read the real-life box again and add what the parasite does.")
check "marks saved"                        "303" "$R"
check "  three notes stored"               "You linked blood cells to malaria." "$(sql "SELECT did_well FROM submission_answers WHERE submission_id=$SUB AND question_id=$QS")"
refute "queue now empty for it"            "Rose Hill" "$(curl -s -b "$TJ" "$BASE/studio/marking")"
D=$(curl -s -b "$R1" "$BASE/dashboard")
check "dashboard: latest feedback"         "Your latest feedback" "$D"
check "  lists the checkpoint"             "Checkpoint 1: E2E Cells" "$D"
RS=$(curl -s -b "$R1" "$BASE/assessments/$CP1/result")
check "result: Partly there"               "</svg> Partly there" "$RS"
check "  Got it for the right answer"      "</svg> Got it" "$RS"
check "  what you did well"                "What you did well" "$RS"
check "  where it went off track"          "Where it went off track" "$RS"
check "  next step"                        "Your next step" "$RS"
check "  encouraging headline"             "Good progress" "$RS"
refute "  no red cross"                    "✗" "$RS"

echo "Re-check requests"
check "re-check offered"                   "I think my answer deserves another look" "$RS"
check "reason must be clear"               "explain clearly why" "$(post "$R1" "/assessments/$CP1/result" "/assessments/$CP1/recheck" --data-urlencode "question_id=$QS" --data-urlencode "reason=please" >/dev/null; curl -s -b "$R1" "$BASE/assessments/$CP1/result")"
post "$R1" "/assessments/$CP1/result" "/assessments/$CP1/recheck" --data-urlencode "question_id=$QS" --data-urlencode "reason=I said they look for malaria in the cells, which is the parasite in red blood cells." >/dev/null
check "request recorded"                   "open" "$(sql "SELECT status FROM recheck_requests WHERE submission_id=$SUB")"
check "  student sees it waiting"          "Waiting for the tutor’s reply" "$(curl -s -b "$R1" "$BASE/assessments/$CP1/result")"
check "  only one open per answer"         "already asked" "$(post "$R1" "/assessments/$CP1/result" "/assessments/$CP1/recheck" --data-urlencode "question_id=$QS" --data-urlencode "reason=Asking again about the same answer please." >/dev/null; curl -s -b "$R1" "$BASE/assessments/$CP1/result")"
check "  full marks cannot be re-checked"  "already has full marks" "$(post "$R1" "/assessments/$CP1/result" "/assessments/$CP1/recheck" --data-urlencode "question_id=$QM" --data-urlencode "reason=I want more marks on this one too." >/dev/null; curl -s -b "$R1" "$BASE/assessments/$CP1/result")"
check "tutor notified"                     "Re-check asked" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "marking page shows open re-checks"  "re-check request" "$(curl -s -b "$TJ" "$BASE/studio/marking")"
RID=$(sql "SELECT id FROM recheck_requests WHERE submission_id=$SUB")
check "reply page shows the reason"        "parasite in red blood cells" "$(curl -s -b "$TJ" "$BASE/studio/rechecks/$RID")"
check "too-high new mark refused"          "422" "$(post "$TJ" "/studio/rechecks/$RID" "/studio/rechecks/$RID" --data-urlencode decision=changed --data-urlencode new_score=9 --data-urlencode "tutor_reply=Fair point.")"
post "$TJ" "/studio/rechecks/$RID" "/studio/rechecks/$RID" --data-urlencode decision=changed --data-urlencode new_score=3.5 --data-urlencode "tutor_reply=You are right, you did mention the parasite. Well argued." >/dev/null
check "mark changed and total recounted"   "changed 3.50 4.50" "$(sql "SELECT r.status, sa.manual_score, s.score FROM recheck_requests r JOIN submission_answers sa ON sa.submission_id=r.submission_id AND sa.question_id=r.question_id JOIN submissions s ON s.id=r.submission_id WHERE r.id=$RID")"
RS=$(curl -s -b "$R1" "$BASE/assessments/$CP1/result")
check "student sees the decision"          "Mark changed to 3.5" "$RS"
check "  and the reply"                    "Well argued." "$RS"
check "  notified"                         "Mark changed: Checkpoint 1" "$(curl -s -b "$R1" "$BASE/notifications")"
sql "UPDATE submissions SET marked_at = NOW() - INTERVAL 8 DAY, submitted_at = NOW() - INTERVAL 8 DAY WHERE id=$SUB"
refute "after 7 days: no re-check offer"   "I think my answer deserves another look" "$(curl -s -b "$R1" "$BASE/assessments/$CP1/result")"
check "  and requests refused"             "within 7 days" "$(post "$R1" "/assessments/$CP1/result" "/assessments/$CP1/recheck" --data-urlencode "question_id=$QS" --data-urlencode "reason=Trying again after the window closed." >/dev/null; curl -s -b "$R1" "$BASE/assessments/$CP1/result")"

echo "Notes on the board"
BL=$(curl -s -b "$TJ" "$BASE/studio/notes/board-list")
check "board list has the notes"           "\"title\":\"E2E Cells\"" "$BL"
check "  as JSON"                          "application/json" "$(curl -s -o /dev/null -D - -b "$TJ" "$BASE/studio/notes/board-list" | tr -d '\r' | grep -i '^content-type')"
check "students kept out of the list"      "403" "$(code -b "$R1" "$BASE/studio/notes/board-list")"
BN=$(curl -s -b "$TJ" "$BASE/studio/notes/$NOTE/board")
check "note text for the board"            "All living things are made of cells" "$BN"
check "  pictures by caption"              "\"type\":\"image\"" "$BN"
refute "  checkpoints left out"            "checkpoint" "$BN"
check "students kept out of the note text" "403" "$(code -b "$R1" "$BASE/studio/notes/$NOTE/board")"
check "unknown note"                       "404" "$(code -b "$TJ" "$BASE/studio/notes/999999/board")"
post "$TJ" "/studio/notes/$NOTE" "/studio/notes/$NOTE/text" --data-urlencode "body=| Metal | With water |
|---|---|
| Sodium | vigorous |

[table: indicators]

\Delta H = frac{1}{2}" >/dev/null
ED=$(curl -s -b "$TJ" "$BASE/studio/notes/$NOTE")
check "editor has the Insert toolbar"     'js-insert-bar' "$ED"
check "  with standard tables to choose"  'value="bondEnthalpy"' "$ED"
check "a typed table is drawn"            '<th>Metal</th><th>With water</th>' "$ED"
check "a standard table is put in"        '<caption>Indicator colours</caption>' "$ED"
check "symbols and fractions"             'Δ H = <span class="frac">' "$ED"
check "board text has the table written out" '| Litmus | red | purple | blue |' "$(curl -s -b "$TJ" "$BASE/studio/notes/$NOTE/board")"
check "reference tables list (JSON)"      '"bondEnthalpy":"Average bond enthalpies"' "$(curl -s -b "$TJ" "$BASE/studio/reference")"
check "one reference table (JSON)"        '"title":"Standard enthalpies of combustion' "$(curl -s -b "$TJ" "$BASE/studio/reference/enthalpyCombustion")"
check "  unknown table"                   "404" "$(code -b "$TJ" "$BASE/studio/reference/nothing")"
check "  students kept out"               "403" "$(code -b "$R1" "$BASE/studio/reference")"
LAB=$(curl -s -b "$TJ" "$BASE/studio/lab")
check "board has a Notes button"           'id="notes-open"' "$LAB"
check "  a Shapes panel"                   'id="shapes-panel"' "$LAB"
check "  a bigger typing box"              '<textarea id="text-input"' "$LAB"
check "  and full screen"                  'id="fullscreen"' "$LAB"
check "  a Tables panel"                   'id="tables-panel"' "$LAB"
check "  drag tools (line)"                'data-tool="line"' "$LAB"
SP=$(curl -s -b "$TJ" "$BASE/studio/notes/$NOTE")
check "back button goes up a level"        'href="/studio/notes" data-back' "$SP"
check "reading progress bar"               'class="scroll-progress"' "$SP"
refute "no back button on the home page"   'data-back' "$(curl -s "$BASE/")"

echo "Looking after the notes"
check "checkpoint with answers kept"       "cannot be deleted" "$(post "$TJ" "$E" "$E/blocks/$B1/delete" >/dev/null; curl -s -b "$TJ" "$BASE$E")"
post "$TJ" "$E" "$E/unpublish" >/dev/null
check "unpublished: students cannot open"  "404" "$(code -b "$R1" "$BASE/notes/$NOTE")"

echo
echo "Passed: $PASS  Failed: $FAIL"
[[ $FAIL -eq 0 ]]
