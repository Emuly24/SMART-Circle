#!/usr/bin/env bash
# End-to-end test of the SMART Circle Team, "Try as" practice accounts, role-aware home pages,
# settings, the database backup and seats per form.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-team.sh
# Uses team tutors +26598900000x and students +26598800000x.
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
setup() { # jar phone code password
    local T; T=$(token "$1" "$BASE/welcome")
    curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "code=$3" "$BASE/welcome"
    T=$(token "$1" "$BASE/welcome/password")
    curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "password=$4" --data-urlencode "password_confirm=$4" "$BASE/welcome/password"
}
nav() { sed -n '/id="site-nav"/,/<\/nav>/p'; }
cleanup() {
    sql "UPDATE settings SET value='' WHERE name='whatsapp_community'; UPDATE settings SET value='+265881193707' WHERE name='tutor_whatsapp'"
    sql "DELETE FROM notes WHERE title LIKE 'E2E team %'; DELETE FROM questions WHERE prompt LIKE 'E2E team %'; DELETE FROM assessments WHERE title LIKE 'E2E team %'; DELETE FROM conversations WHERE subject LIKE 'E2E team %'"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts"
sql "DELETE FROM notes WHERE title LIKE 'E2E team %'; DELETE FROM questions WHERE prompt LIKE 'E2E team %'; DELETE FROM assessments WHERE title LIKE 'E2E team %'; DELETE FROM conversations WHERE subject LIKE 'E2E team %'"
sql "DELETE FROM conversations WHERE student_id IN (SELECT id FROM users WHERE phone LIKE '+26598800000%')"
sql "DELETE FROM users WHERE phone LIKE '+26598900000%' OR phone LIKE '+26598800000%'"
HASH=$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')
for n in 1 2; do
    TRACK=$([[ $n == 1 ]] && echo science || echo humanities)
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, last_login_at) VALUES ('student', 'Team Student $n', '+26598800000$n', 'female', '$HASH', NOW())"
    sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_confirmed_at) SELECT id, 3, '$TRACK', NOW(), NOW() FROM users WHERE phone='+26598800000$n'"
done
S1=$(sql "SELECT id FROM users WHERE phone='+265988000001'"); S2=$(sql "SELECT id FROM users WHERE phone='+265988000002'")
TJ="$WORK/t.txt"; J1="$WORK/s1.txt"; MJ="$WORK/m.txt"; DJ="$WORK/d.txt"
login "$TJ" 0999123456 correct-horse-1 >/dev/null; login "$J1" 0988000001 student-pass-1 >/dev/null

echo "Role-aware home pages and menus"
G=$(curl -s "$BASE/")
check "guests see the public home page"    "How SMART Circle works" "$G"
check "  with the application buttons"     'href="/apply/status"' "$G"
check "  with About in the menu"           'href="/about"' "$(nav <<<"$G")"
A=$(curl -s -b "$TJ" "$BASE/")
check "admin sees the same home page"      "How SMART Circle works" "$A"
check "  with Open my dashboard"           "Open my dashboard" "$A"
refute "  no application buttons"          'href="/apply/status"' "$A"
check "  admin summary on the dashboard"   "dashboard-summary" "$(curl -s -b "$TJ" "$BASE/dashboard")"
check "  admin menu: Try as"               "Try as" "$(nav <<<"$A")"
refute "  no About in the admin menu"      'href="/about"' "$(nav <<<"$A")"
refute "  no student Topics link"          'href="/topics"' "$(nav <<<"$A")"
SH=$(curl -s -b "$J1" "$BASE/")
check "student sees the same home page"    "How SMART Circle works" "$SH"
check "  with Open my dashboard"           "Open my dashboard" "$SH"
refute "  no Apply button"                 'href="/apply"' "$SH"
check "  student menu: Notes"              'href="/notes"' "$(nav <<<"$SH")"
refute "  no admin links"                  "/studio/" "$(nav <<<"$SH")"

echo "Inviting a team tutor"
check "invalid invitation refused"         "Choose at least one subject" "$(post "$TJ" /studio/team /studio/team --data-urlencode "full_name=Mercy Mentor" --data-urlencode phone=0989000001 >/dev/null; cat "$WORK/last.html")"
check "admin invites (Physics)"            "303" "$(post "$TJ" /studio/team /studio/team --data-urlencode "full_name=Mercy Mentor" --data-urlencode phone=0989000001 --data-urlencode "subjects[]=physics")"
MID=$(sql "SELECT id FROM users WHERE phone='+265989000001'")
P=$(curl -s -b "$TJ" "$BASE/studio/team/$MID")
CODE=$(grep -oE 'class="reference">[^<]+' <<<"$P" | sed 's/.*>//; s/ //g')
check "  setup code shown"                 "8" "${#CODE}"
check "  WhatsApp message ready"           "https://wa.me/265989000001?text=Hello%20Mercy" "$P"
check "  title can be set by the admin"   'action="/studio/people/' "$P"
post "$TJ" "/studio/team/$MID" "/studio/people/$MID/title" --data-urlencode title=Ms --data-urlencode "back=/studio/team/$MID" >/dev/null
check "  title saved"                      "Ms" "$(sql "SELECT honorific FROM users WHERE id=$MID")"
check "  students' titles cannot be set"   "Titles are for teachers and tutors" "$(post "$TJ" "/studio/team/$MID" "/studio/people/$(sql "SELECT id FROM users WHERE role='student' LIMIT 1")/title" --data-urlencode title=Mr --data-urlencode "back=/studio/team/$MID" >/dev/null; curl -s -b "$TJ" "$BASE/studio/team/$MID")"
check "  role is team, status invited"     "team invited" "$(sql "SELECT u.role, t.status FROM users u JOIN team_profiles t ON t.user_id=u.id WHERE u.id=$MID")"
check "team tutor sets a password"         "303" "$(setup "$MJ" 0989000001 "$CODE" team-pass-123 | cut -c1-3)"
check "  must read the agreement first"    "/team/agreement" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$MJ" "$BASE/studio/lessons")"
AG=$(curl -s -b "$MJ" "$BASE/team/agreement")
check "  agreement shows the principles"   "No favours of any kind" "$AG"
check "  and the duties"                   "within 7 days" "$AG"
check "  wrong name refused"               "Type your full name" "$(post "$MJ" /team/agreement /team/agreement --data-urlencode decision=agree --data-urlencode "signed_name=Someone" >/dev/null; cat "$WORK/last.html")"
check "  agrees"                           "303" "$(post "$MJ" /team/agreement /team/agreement --data-urlencode decision=agree --data-urlencode "signed_name=mercy mentor")"
check "  active, version recorded"         "active 2026-11" "$(sql "SELECT status, agreement_version FROM team_profiles WHERE user_id=$MID")"
D=$(curl -s -b "$MJ" "$BASE/dashboard")
check "team dashboard"                     "Your subjects: Physics" "$D"
check "team home page"                     "Open my dashboard" "$(curl -s -b "$MJ" "$BASE/")"
check "  team summary on the dashboard"    "dashboard-summary" "$D"
check "  team menu: My lessons"            "My lessons" "$(curl -s -b "$MJ" "$BASE/" | nav)"

echo "Team tutors work only in their subjects"
check "create Physics notes"               "303" "$(post "$MJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E team physics notes" --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode topic_id=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' LIMIT 1") | cut -c1-3)"
check "  Chemistry refused"                "403" "$(post "$MJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E team chem notes" --data-urlencode form=3 --data-urlencode subject=chemistry --data-urlencode topic_id=$(sql "SELECT id FROM topics WHERE form=3 AND subject='chemistry' LIMIT 1") | cut -c1-3)"
check "admin makes Chemistry notes"        "303" "$(post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E team admin chemistry" --data-urlencode form=3 --data-urlencode subject=chemistry --data-urlencode topic_id=$(sql "SELECT id FROM topics WHERE form=3 AND subject='chemistry' LIMIT 1") | cut -c1-3)"
CHEM=$(sql "SELECT id FROM notes WHERE title='E2E team admin chemistry'")
# A team tutor can upload a recording (upload ids are hex, not numbers).
php -r '$h = json_encode(["version"=>1,"durationMs"=>1000,"events"=>[]]); file_put_contents($argv[1], "SCL1" . pack("V", strlen($h)) . $h . str_repeat("x", 3000));' "$WORK/t.scl"
TCSRF=$(curl -s -b "$MJ" -c "$MJ" "$BASE/studio/lab" | grep -oE 'name="csrf-token" content="[^"]+"' | sed -E 's/.*content="//;s/"//')
TUP=$(curl -s -b "$MJ" -c "$MJ" -H "X-CSRF-Token: $TCSRF" -H 'Content-Type: application/json' \
    -d "{\"client_key\":\"e2e-team-up\",\"title\":\"E2E team upload\",\"size\":$(stat -c %s "$WORK/t.scl"),\"sha256\":\"$(sha256sum "$WORK/t.scl" | cut -d' ' -f1)\"}" "$BASE/studio/uploads" \
    | php -r 'echo json_decode(stream_get_contents(STDIN), true)["upload_id"] ?? "";')
curl -s -o /dev/null -b "$MJ" -c "$MJ" -H "X-CSRF-Token: $TCSRF" -H 'Content-Type: application/octet-stream' --data-binary "@$WORK/t.scl" "$BASE/studio/uploads/$TUP/chunk?offset=0"
check "team tutor uploads a recording"     '"lesson_id":' "$(curl -s -b "$MJ" -c "$MJ" -H "X-CSRF-Token: $TCSRF" -X POST "$BASE/studio/uploads/$TUP/finish")"
sql "DELETE FROM lessons WHERE title='E2E team upload'"
NL=$(curl -s -b "$MJ" "$BASE/studio/notes")
check "  team sees their notes"            "E2E team physics notes" "$NL"
refute "  but not Chemistry"               "E2E team admin chemistry" "$NL"
check "  nor open it"                      "403" "$(code -b "$MJ" "$BASE/studio/notes/$CHEM")"
check "  nor change it"                    "403" "$(post "$MJ" /studio/notes "/studio/notes/$CHEM/text" --data-urlencode "body=sneaky" | cut -c1-3)"
QB=$(curl -s -b "$MJ" "$BASE/studio/questions?form=3&subject=chemistry")
check "question bank falls back to Physics" "Physics" "$QB"
refute "  no Chemistry choice"             '>Chemistry<' "$QB"
sql "INSERT INTO assessments (kind, title, form, subject, status) VALUES ('assignment', 'E2E team chem work', 3, 'chemistry', 'published'), ('assignment', 'E2E team phys work', 3, 'physics', 'published')"
for t in chem phys; do sql "INSERT INTO submissions (assessment_id, user_id, submitted_at, status) SELECT id, $S1, NOW(), 'submitted' FROM assessments WHERE title='E2E team $t work'"; done
MQ=$(curl -s -b "$MJ" "$BASE/studio/marking")
check "marking: Physics work listed"       "E2E team phys work" "$MQ"
refute "  Chemistry work not listed"       "E2E team chem work" "$MQ"
CSUB=$(sql "SELECT s.id FROM submissions s JOIN assessments a ON a.id=s.assessment_id WHERE a.title='E2E team chem work'")
PSUB=$(sql "SELECT s.id FROM submissions s JOIN assessments a ON a.id=s.assessment_id WHERE a.title='E2E team phys work'")
check "  cannot open Chemistry work"       "403" "$(code -b "$MJ" "$BASE/studio/submissions/$CSUB")"
check "  can open Physics work"            "200" "$(code -b "$MJ" "$BASE/studio/submissions/$PSUB")"
check "  admin sees both"                  "E2E team chem work" "$(curl -s -b "$TJ" "$BASE/studio/marking")"
for p in /studio/admissions /studio/students /studio/team /studio/settings /studio/practice /studio/year-end /studio/support /studio/grades /studio/topics /studio/results; do
    check "  admin-only: $p"                "403" "$(code -b "$MJ" "$BASE$p")"
done

echo "Team messages and announcements"
check "announce to another subject refused" "Choose who should get it" "$(post "$MJ" /messages/new /messages/announce --data-urlencode audience=subject:chemistry --data-urlencode "subject=E2E team bad" --data-urlencode "body=x" >/dev/null; cat "$WORK/last.html")"
check "  nor to all students"              "Choose who should get it" "$(post "$MJ" /messages/new /messages/announce --data-urlencode audience=students --data-urlencode "subject=E2E team bad2" --data-urlencode "body=x" >/dev/null; cat "$WORK/last.html")"
check "announce to Physics students"       "303" "$(post "$MJ" /messages/new /messages/announce --data-urlencode audience=subject:physics --data-urlencode "subject=E2E team physics practical" --data-urlencode "body=Bring a ruler.")"
ANN=$(sql "SELECT id FROM conversations WHERE subject='E2E team physics practical'")
check "  science student included"         "1" "$(sql "SELECT COUNT(*) FROM conversation_members WHERE conversation_id=$ANN AND user_id=$S1")"
check "  humanities student left out"      "0" "$(sql "SELECT COUNT(*) FROM conversation_members WHERE conversation_id=$ANN AND user_id=$S2")"
check "students can write to team tutors"  "Mercy Mentor" "$(curl -s -b "$J1" "$BASE/messages/new")"

echo "Private rooms: team tutors only when added"
check "admin invites a student"            "303" "$(post "$TJ" "/studio/students/$S1" "/studio/students/$S1/invite" --data-urlencode "body=E2E team: how are things at home?" | cut -c1-3)"
RID=$(sql "SELECT id FROM conversations WHERE kind='support' AND student_id=$S1")
check "  team tutor cannot open it"        "404" "$(code -b "$MJ" "$BASE/studio/support/$RID")"
check "admin adds the team tutor"          "303" "$(post "$TJ" "/studio/support/$RID" "/studio/support/$RID/members" --data-urlencode user_id=$MID | cut -c1-3)"
check "  team tutor reads it"              "how are things at home" "$(curl -s -b "$MJ" "$BASE/studio/support/$RID")"
check "  and replies"                      "303" "$(post "$MJ" "/studio/support/$RID" "/studio/support/$RID" --data-urlencode "body=I can help with physics revision." | cut -c1-3)"
check "  student told who joined"          "A team tutor has joined your private room" "$(curl -s -b "$J1" "$BASE/notifications")"
check "  student sees them in the room"    "Mercy Mentor" "$(curl -s -b "$J1" "$BASE/support")"
check "  team cannot add others"           "403" "$(post "$MJ" "/studio/support/$RID" "/studio/support/$RID/members" --data-urlencode user_id=$MID | cut -c1-3)"

echo "Removing, and declining"
check "admin changes subjects"             "303" "$(post "$TJ" "/studio/team/$MID" "/studio/team/$MID/subjects" --data-urlencode "subjects[]=physics" --data-urlencode "subjects[]=chemistry" | cut -c1-3)"
check "  now sees Chemistry notes"         "E2E team admin chemistry" "$(curl -s -b "$MJ" "$BASE/studio/notes")"
check "admin removes the team tutor"       "303" "$(post "$TJ" "/studio/team/$MID" "/studio/team/$MID/status" --data-urlencode active=0 | cut -c1-3)"
check "  signed out at once"               "/login" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$MJ" "$BASE/dashboard")"
check "  cannot sign in"                   "422" "$(login "$WORK/m2.txt" 0989000001 team-pass-123)"
check "  their notes are kept"             "1" "$(sql "SELECT COUNT(*) FROM notes WHERE title='E2E team physics notes'")"
post "$TJ" /studio/team /studio/team --data-urlencode "full_name=Daniel Decline" --data-urlencode phone=0989000002 --data-urlencode "subjects[]=biology" >/dev/null
DID=$(sql "SELECT id FROM users WHERE phone='+265989000002'")
DCODE=$(curl -s -b "$TJ" "$BASE/studio/team/$DID" | grep -oE 'class="reference">[^<]+' | sed 's/.*>//; s/ //g')
setup "$DJ" 0989000002 "$DCODE" team-pass-456 >/dev/null
check "a tutor declines"                   "Thank you" "$(post "$DJ" /team/agreement /team/agreement --data-urlencode decision=decline >/dev/null; cat "$WORK/last.html")"
check "  account closed"                   "declined disabled" "$(sql "SELECT t.status, u.status FROM team_profiles t JOIN users u ON u.id=t.user_id WHERE u.id=$DID")"

echo "Try as (practice accounts)"
check "practice page"                      "Try as this person" "$(curl -s -b "$TJ" "$BASE/studio/practice")"
check "students cannot open it"            "403" "$(code -b "$J1" "$BASE/studio/practice")"
check "enter as a Form 3 student"          "303" "$(post "$TJ" /studio/practice /studio/practice/enter --data-urlencode as=student3 | cut -c1-3)"
PD=$(curl -s -b "$TJ" "$BASE/dashboard")
check "  practice bar shown"               "Practice mode" "$PD"
check "  student dashboard"                "Practice Student (Form 3 science)" "$PD"
check "  a taught topic is open"           "Taught at your school" "$(curl -s -b "$TJ" "$BASE/topics/$(sql "SELECT c.topic_id FROM topic_coverage c JOIN schools s ON s.id=c.school_id WHERE s.is_practice=1 AND c.status='covered' AND c.topic_id IN (SELECT id FROM topics WHERE form=3 AND subject='physics') LIMIT 1")")"
check "  admin pages closed"               "403" "$(code -b "$TJ" "$BASE/studio/students")"
check "  cannot jump to another account"   "403" "$(post "$TJ" /dashboard /studio/practice/enter --data-urlencode as=team | cut -c1-3)"
check "back to admin"                      "303" "$(post "$TJ" /dashboard /practice/leave | cut -c1-3)"
check "  admin again"                      "dashboard-summary" "$(curl -s -b "$TJ" "$BASE/dashboard")"
refute "  no practice bar"                 "Practice mode" "$(curl -s -b "$TJ" "$BASE/dashboard")"
post "$TJ" /studio/practice /studio/practice/enter --data-urlencode as=partner >/dev/null
check "as a partner teacher"               "Practice Secondary School" "$(curl -s -b "$TJ" "$BASE/dashboard")"
post "$TJ" /dashboard /practice/leave >/dev/null
post "$TJ" /studio/practice /studio/practice/enter --data-urlencode as=team >/dev/null
check "as a team tutor"                    "Mathematics, Physics" "$(curl -s -b "$TJ" "$BASE/dashboard")"
post "$TJ" /dashboard /practice/leave >/dev/null
check "  practice users exist"             "7" "$(sql "SELECT COUNT(*) FROM users WHERE is_practice=1")"
check "practice accounts cannot log in"    "422" "$(login "$WORK/p.txt" 0100000001 anything123)"
check "reset practice data"                "303" "$(post "$TJ" /studio/practice /studio/practice/reset | cut -c1-3)"
check "  all gone"                         "0 0" "$(sql "SELECT (SELECT COUNT(*) FROM users WHERE is_practice=1), (SELECT COUNT(*) FROM schools WHERE is_practice=1)")"

echo "Settings and backup"
check "bad community link refused"         "chat.whatsapp.com" "$(post "$TJ" /studio/settings /studio/settings --data-urlencode tutor_whatsapp=0881193707 --data-urlencode whatsapp_community=https://example.com/x >/dev/null; cat "$WORK/last.html")"
check "bad phone refused"                  "Use a Malawian number" "$(post "$TJ" /studio/settings /studio/settings --data-urlencode tutor_whatsapp=12345 >/dev/null; cat "$WORK/last.html")"
check "save settings"                      "303" "$(post "$TJ" /studio/settings /studio/settings --data-urlencode tutor_whatsapp=0881193707 --data-urlencode whatsapp_community=https://chat.whatsapp.com/AbCdEf123 | cut -c1-3)"
check "  number stored canonically"        "+265881193707" "$(sql "SELECT value FROM settings WHERE name='tutor_whatsapp'")"
check "  students see the community"       "https://chat.whatsapp.com/AbCdEf123" "$(curl -s -b "$J1" "$BASE/dashboard")"
curl -s -D "$WORK/h.txt" -o "$WORK/backup.gz" -b "$TJ" --data-urlencode "_token=$(token "$TJ" "$BASE/studio/settings")" "$BASE/studio/backup"
check "backup downloads"                   "attachment; filename=\"smartcircle-backup-" "$(cat "$WORK/h.txt")"
check "  is a full SQL copy"               'CREATE TABLE `users`' "$(gunzip -c "$WORK/backup.gz" | head -c 2000000)"
check "  with the data"                    "Team Student 1" "$(gunzip -c "$WORK/backup.gz")"
refute "  sessions left out"               'CREATE TABLE `sessions`' "$(gunzip -c "$WORK/backup.gz")"
check "  logged"                           "backup_downloaded" "$(sql "SELECT action FROM audit_log ORDER BY id DESC LIMIT 1")"
check "students cannot back up"            "403" "$(post "$J1" /dashboard /studio/backup | cut -c1-3)"

echo "Seats per form"
INTAKE=$(sql "SELECT id FROM intakes WHERE is_active=1 ORDER BY id DESC LIMIT 1")
if [[ -n "$INTAKE" ]]; then
    OLD=$(sql "SELECT COALESCE(seat_plan, 'NULL'), seats_per_group FROM intakes WHERE id=$INTAKE")
    check "seat grid shown"                "plan[1][female]" "$(curl -s -b "$TJ" "$BASE/studio/admissions/intake")"
    post "$TJ" /studio/admissions/intake /studio/admissions/intake --data-urlencode "name=$(sql "SELECT name FROM intakes WHERE id=$INTAKE")" --data-urlencode is_open=1 --data-urlencode seats_per_group=3 \
        --data-urlencode "plan[1][female]=5" --data-urlencode "plan[1][male]=5" --data-urlencode "plan[2][female]=3" --data-urlencode "plan[2][male]=3" \
        --data-urlencode "plan[3][female]=3" --data-urlencode "plan[3][male]=3" --data-urlencode "plan[4][female]=3" --data-urlencode "plan[4][male]=3" >/dev/null
    check "  Form 1 raised to 5 + 5"       '"1":{"female":5,"male":5}' "$(sql "SELECT seat_plan FROM intakes WHERE id=$INTAKE")"
    check "  home page shows 28 places"    "28 places" "$(curl -s "$BASE/")"
    sql "UPDATE intakes SET seat_plan = NULL WHERE id=$INTAKE"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
