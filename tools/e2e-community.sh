#!/usr/bin/env bash
# End-to-end test of the community: lesson discussions (replies, @mentions, #tags, reactions, follows, reports,
# hiding, approved answers), exit tickets, weekly challenges, the "Spotted in real life" wall and badges,
# all under the honesty rule.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-community.sh
# Uses Form 2 Biology, students +26599840000x, schools "E2E Comm ..." and lessons "E2E Comm ...".
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
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
upload() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --form-string "_token=$T" "$@" "$BASE$action"; }
cleanup() {
    sql "DELETE FROM lessons WHERE title LIKE 'E2E Comm %'; DELETE FROM challenges WHERE title LIKE 'E2E Comm %'"
    sql "DELETE FROM users WHERE phone LIKE '+26599840000%'; DELETE FROM schools WHERE name LIKE 'E2E Comm %'"
    sql "DELETE FROM tags WHERE name IN ('photosynthesis', 'e2ecomm')"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM lessons WHERE title LIKE 'E2E Comm %'; DELETE FROM challenges WHERE title LIKE 'E2E Comm %'; DELETE FROM users WHERE phone LIKE '+26599840000%'; DELETE FROM schools WHERE name LIKE 'E2E Comm %'"
sql "INSERT INTO schools (name, district) VALUES ('E2E Comm Taught', 'Salima'), ('E2E Comm Untaught', 'Chitipa')"
TAUGHT=$(sql "SELECT id FROM schools WHERE name='E2E Comm Taught'"); UNTAUGHT=$(sql "SELECT id FROM schools WHERE name='E2E Comm Untaught'")
TOPIC=$(sql "SELECT id FROM topics WHERE form=2 AND subject='biology' AND parent_id IS NULL ORDER BY sort_order LIMIT 1")
sql "INSERT INTO topic_coverage (school_id, topic_id, status, source) VALUES ($TAUGHT, $TOPIC, 'covered', 'tutor')"
HASH=$(php -r 'echo password_hash("comm-pass-1", PASSWORD_DEFAULT);')
student() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash) VALUES ('student', '$2', '$1', 'female', '$HASH')"
    sql "INSERT INTO student_profiles (user_id, form, track, school_id, agreement_accepted_at, guardian_confirmed_at) SELECT id, 2, 'general', $3, NOW(), NOW() FROM users WHERE phone='$1'"
}
student +265998400001 "Alinafe Comm" "$TAUGHT"
student +265998400002 "Bertha Comm" "$TAUGHT"
student +265998400003 "Chisomo Comm" "$UNTAUGHT"
A=$(sql "SELECT id FROM users WHERE phone='+265998400001'"); B=$(sql "SELECT id FROM users WHERE phone='+265998400002'")
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
sql "INSERT INTO lessons (title, form, subject, topic_id, status, created_by, published_at, sha256, summary) VALUES ('E2E Comm Photosynthesis', 2, 'biology', $TOPIC, 'published', $TID, NOW(), REPEAT('0', 64), 'How plants make food')"
L=$(sql "SELECT id FROM lessons WHERE title='E2E Comm Photosynthesis'")
TJ="$WORK/t.txt"; AJ="$WORK/a.txt"; BJ="$WORK/b.txt"; CJ="$WORK/c.txt"
login "$TJ" 0999123456 correct-horse-1; login "$AJ" 0998400001 comm-pass-1; login "$BJ" 0998400002 comm-pass-1; login "$CJ" 0998400003 comm-pass-1
LP="/lessons/$L"

echo "Lesson discussion"
P=$(curl -s -b "$AJ" "$BASE$LP")
check "discussion under the lesson"        "Questions and discussion" "$P"
check "exit ticket under the lesson"       "Exit ticket" "$P"
post "$AJ" "$LP" "$LP/comments" --data-urlencode "body=E2E-Q Why are leaves green? #photosynthesis" --data-urlencode "time=1:30" >/dev/null
Q=$(sql "SELECT id FROM comments WHERE body LIKE 'E2E-Q%'")
P=$(curl -s -b "$BJ" "$BASE$LP")
check "classmate sees the question"        "E2E-Q Why are leaves green?" "$P"
check "  by first name and form"           "Alinafe (Form 2)" "$P"
check "  at the minute"                    "at 1:30" "$P"
check "  tag linked"                       'href="/community/tags/photosynthesis"' "$P"
refute "  surname never shown"             "Alinafe Comm" "$P"
check "tutor told about the question"      "New question on E2E Comm Photosynthesis" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "asker follows the discussion"       "Stop following" "$(curl -s -b "$AJ" "$BASE$LP")"
check "honesty: untaught school locked"    "403" "$(code -b "$CJ" "$BASE$LP")"
post "$CJ" "$LP" "$LP/comments" --data-urlencode "body=E2E-SNEAK hello" >/dev/null
check "  and cannot post"                  "0" "$(sql "SELECT COUNT(*) FROM comments WHERE body LIKE 'E2E-SNEAK%'")"
check "  nor see it by tag"                "Nothing with this tag" "$(curl -s -b "$CJ" "$BASE/community/tags/photosynthesis")"
check "taught student sees it by tag"      "E2E-Q Why are leaves green?" "$(curl -s -b "$BJ" "$BASE/community/tags/photosynthesis")"
post "$BJ" "$LP" "$LP/comments" --data-urlencode "parent_id=$Q" --data-urlencode "body=E2E-R @Alinafe because chlorophyll reflects green light" >/dev/null
R=$(sql "SELECT id FROM comments WHERE body LIKE 'E2E-R%'")
check "reply threaded"                     "$Q" "$(sql "SELECT parent_id FROM comments WHERE id=$R")"
check "mention recorded"                   "$A" "$(sql "SELECT user_id FROM mentions WHERE comment_id=$R")"
check "  mentioned student told"           "Bertha mentioned you" "$(curl -s -b "$AJ" "$BASE/notifications")"
check "empty comment refused"              "Write your question" "$(post "$AJ" "$LP" "$LP/comments" --data-urlencode "body= " >/dev/null; curl -s -b "$AJ" "$BASE$LP")"
post "$BJ" "$LP" "/comments/$Q/react" --data-urlencode kind=same_question --data-urlencode "back=$LP" >/dev/null
check "same question counted"              "Same question · 1" "$(curl -s -b "$AJ" "$BASE$LP")"
post "$BJ" "$LP" "/comments/$Q/react" --data-urlencode kind=same_question --data-urlencode "back=$LP" >/dev/null
check "  toggles off"                      "0" "$(sql "SELECT COUNT(*) FROM reactions WHERE comment_id=$Q")"
post "$AJ" "$LP" "/comments/$R/react" --data-urlencode kind=thanks --data-urlencode "back=$LP" >/dev/null
check "thanks counted"                     "Thanks · 1" "$(curl -s -b "$BJ" "$BASE$LP")"
check "untaught cannot react"              "0" "$(post "$CJ" /dashboard "/comments/$Q/react" --data-urlencode kind=thanks >/dev/null; sql "SELECT COUNT(*) FROM reactions WHERE comment_id=$Q")"
post "$TJ" "$LP" "/comments/$R/approve" >/dev/null
check "tutor approves the answer"          "Tutor approved" "$(curl -s -b "$AJ" "$BASE$LP")"
check "  helper told"                      "The tutor approved your answer" "$(curl -s -b "$BJ" "$BASE/notifications")"
check "students cannot approve (403)"      "403" "$(post "$AJ" "$LP" "/comments/$R/approve")"

echo "Reports and hiding"
post "$BJ" "$LP" "$LP/comments" --data-urlencode "body=E2E-RUDE something unkind" >/dev/null
RUDE=$(sql "SELECT id FROM comments WHERE body LIKE 'E2E-RUDE%'")
check "report needs a reason"              "Please say briefly" "$(post "$AJ" "$LP" "/comments/$RUDE/report" --data-urlencode "reason=" --data-urlencode "back=$LP" >/dev/null; curl -s -b "$AJ" "$BASE$LP")"
post "$AJ" "$LP" "/comments/$RUDE/report" --data-urlencode "reason=Unkind words" --data-urlencode "back=$LP" >/dev/null
check "tutor told"                         "A comment was reported" "$(curl -s -b "$TJ" "$BASE/notifications")"
P=$(curl -s -b "$TJ" "$BASE/studio/community")
check "moderation page lists it"           "Unkind words" "$P"
check "  with the comment"                 "E2E-RUDE something unkind" "$P"
check "students kept out (403)"            "403" "$(code -b "$AJ" "$BASE/studio/community")"
post "$TJ" /studio/community "/comments/$RUDE/hide" --data-urlencode back=community >/dev/null
refute "hidden from students"              "E2E-RUDE" "$(curl -s -b "$AJ" "$BASE$LP")"
check "  tutor still sees it, marked"      "Hidden from students" "$(curl -s -b "$TJ" "$BASE$LP")"
check "  report closed"                    "resolved" "$(sql "SELECT status FROM content_reports WHERE target_id=$RUDE")"
post "$AJ" "$LP" "$LP/follow" --data-urlencode on=0 >/dev/null
check "unfollow"                           "0" "$(sql "SELECT COUNT(*) FROM follows WHERE user_id=$A AND target_type='lesson' AND target_id='$L'")"

echo "Exit tickets"
check "understanding required"             "Choose how well" "$(post "$AJ" "$LP" "$LP/exit-ticket" --data-urlencode understanding=9 >/dev/null; curl -s -b "$AJ" "$BASE$LP")"
post "$AJ" "$LP" "$LP/exit-ticket" --data-urlencode understanding=4 --data-urlencode "real_life_use=E2E-USE maize grows in the sun" --data-urlencode "still_unclear=E2E-UNCLEAR the dark reaction" >/dev/null
post "$BJ" "$LP" "$LP/exit-ticket" --data-urlencode understanding=2 >/dev/null
check "saved, can be updated"              "Thank you! You can change your answers" "$(curl -s -b "$AJ" "$BASE$LP")"
E=$(curl -s -b "$TJ" "$BASE/studio/lessons/$L")
check "tutor sees the summary"             "Exit tickets (2)" "$E"
check "  average understanding"            "3 / 5" "$E"
check "  what is still unclear"            "E2E-UNCLEAR the dark reaction" "$E"
check "untaught student cannot (404)"      "404" "$(post "$CJ" /dashboard "$LP/exit-ticket" --data-urlencode understanding=3)"

echo "Weekly challenges"
check "community page"                     "This week's challenges" "$(curl -s -b "$AJ" "$BASE/community")"
check "menu has Community"                 'href="/community"' "$(curl -s -b "$AJ" "$BASE/dashboard")"
check "needs a real problem"               "422" "$(post "$TJ" /studio/community /studio/community/challenges --data-urlencode form=2 --data-urlencode subject=biology --data-urlencode "title=E2E Comm X" --data-urlencode body=short)"
post "$TJ" /studio/community /studio/community/challenges --data-urlencode form=2 --data-urlencode subject=biology --data-urlencode "topic_id=$TOPIC" \
    --data-urlencode "title=E2E Comm Shade experiment" --data-urlencode "body=A farmer covers half a bean plant's leaves. What happens after a week, and why?" --data-urlencode days=7 >/dev/null
CH=$(sql "SELECT id FROM challenges WHERE title='E2E Comm Shade experiment'")
check "taught student sees it"             "E2E Comm Shade experiment" "$(curl -s -b "$AJ" "$BASE/community")"
refute "untaught student does not"         "E2E Comm Shade experiment" "$(curl -s -b "$CJ" "$BASE/community")"
check "  nor by link (404)"                "404" "$(code -b "$CJ" "$BASE/community/challenges/$CH")"
post "$AJ" "/community/challenges/$CH" "/community/challenges/$CH" --data-urlencode "body=E2E-ANS-A The covered leaves turn yellow: no light, no food." >/dev/null
post "$BJ" "/community/challenges/$CH" "/community/challenges/$CH" --data-urlencode "body=E2E-ANS-B Less starch is made in the covered half." >/dev/null
P=$(curl -s -b "$AJ" "$BASE/community/challenges/$CH")
check "answer sent"                        "Your answer is in" "$P"
refute "others' answers hidden while open" "E2E-ANS-B" "$P"
check "one answer each"                    "already answered" "$(post "$AJ" "/community/challenges/$CH" "/community/challenges/$CH" --data-urlencode "body=E2E-ANS-A2 again" >/dev/null; curl -s -b "$AJ" "$BASE/community/challenges/$CH")"
check "tutor sees the answers"             "E2E-ANS-B" "$(curl -s -b "$TJ" "$BASE/community/challenges/$CH")"
post "$TJ" "/community/challenges/$CH" "/studio/community/challenges/$CH/explain" --data-urlencode "explanation=E2E-EXPLAIN Light is needed to make starch." >/dev/null
sql "UPDATE challenges SET closes_at = NOW() - INTERVAL 1 HOUR WHERE id=$CH"
P=$(curl -s -b "$AJ" "$BASE/community/challenges/$CH")
check "after closing: everyone's answers"  "E2E-ANS-B" "$P"
check "  and the explanation"              "E2E-EXPLAIN Light is needed" "$P"
check "  no more answers"                  "has closed" "$(post "$BJ" "/community/challenges/$CH" "/community/challenges/$CH" --data-urlencode "body=E2E late answer" >/dev/null; curl -s -b "$BJ" "$BASE/community/challenges/$CH")"

echo "Spotted in real life"
php -r '$i = imagecreatetruecolor(800, 600); imagefill($i, 0, 0, imagecolorallocate($i, 31, 157, 107)); imagejpeg($i, $argv[1], 85);' "$WORK/leaf.jpg"
printf 'nope' > "$WORK/fake.jpg"
check "fake photo refused"                 "422" "$(upload "$AJ" /community/wall /community/wall -F "photo=@$WORK/fake.jpg;type=image/jpeg" --form-string "caption=E2E-WALL fake photo")"
check "caption needed"                     "422" "$(upload "$AJ" /community/wall /community/wall -F "photo=@$WORK/leaf.jpg;type=image/jpeg" --form-string "caption=x")"
upload "$AJ" /community/wall /community/wall -F "photo=@$WORK/leaf.jpg;type=image/jpeg" --form-string "caption=E2E-WALL Green leaves by Lake Malawi" --form-string "topic_id=$TOPIC" >/dev/null
W=$(sql "SELECT id FROM wall_posts WHERE caption LIKE 'E2E-WALL Green%'")
check "waits for the tutor"                "pending" "$(sql "SELECT status FROM wall_posts WHERE id=$W")"
refute "  not on the wall yet"             "E2E-WALL Green" "$(curl -s -b "$BJ" "$BASE/community/wall")"
check "  photo private to its author"      "404" "$(code -b "$BJ" "$BASE/community/wall/$W/photo")"
check "  author can see it"                "200" "$(code -b "$AJ" "$BASE/community/wall/$W/photo")"
check "  tutor told"                       "A new photo for the wall is waiting" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  on the moderation page"           "E2E-WALL Green leaves" "$(curl -s -b "$TJ" "$BASE/studio/community")"
post "$TJ" /studio/community "/studio/community/wall/$W/feature" >/dev/null
check "featured on the wall"               "E2E-WALL Green leaves" "$(curl -s -b "$BJ" "$BASE/community/wall")"
check "  photo opens for classmates"       "image/jpeg" "$(curl -s -D - -o /dev/null -b "$BJ" "$BASE/community/wall/$W/photo")"
check "  author told"                      "Your photo is featured" "$(curl -s -b "$AJ" "$BASE/notifications")"
curl -s -o /dev/null -b "$AJ" "$BASE/community"   # badges are awarded when the community page is opened
post "$BJ" /community/wall "/community/wall/$W/report" --data-urlencode "reason=There is a face in it" >/dev/null
check "wall report reaches the tutor"      "There is a face in it" "$(curl -s -b "$TJ" "$BASE/studio/community")"
post "$TJ" /studio/community "/studio/community/wall/$W/hide" >/dev/null
check "hidden photo gone"                  "404" "$(code -b "$BJ" "$BASE/community/wall/$W/photo")"
check "guests kept out"                    "303" "$(code "$BASE/community/wall")"

echo "Badges"
P=$(curl -s -b "$AJ" "$BASE/community")
check "curious mind earned"                "badge-tile--earned\"><strong>🏅 Curious mind" "$P"
check "  told about it"                    "New badge: Curious mind" "$(curl -s -b "$AJ" "$BASE/notifications")"
check "helper earned (thanked answer)"     "Helper" "$(curl -s -b "$BJ" "$BASE/community" >/dev/null; sql "SELECT GROUP_CONCAT(code) FROM achievements WHERE user_id=$B" | sed 's/helper/Helper/')"
check "featured photo badge"               "featured" "$(sql "SELECT GROUP_CONCAT(code) FROM achievements WHERE user_id=$A")"

echo; echo "Community: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
