#!/usr/bin/env bash
# End-to-end test of guardian details, welcome messages in Chichewa or English, the guardian's
# confirmation link, the monthly update, and profile pictures.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-guardians.sh
# Uses applicants/students +26598700000x.
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
sql "DELETE FROM users WHERE phone LIKE '+26598700000%'; DELETE FROM applications WHERE phone LIKE '+26598700000%'"
TJ="$WORK/t.txt"; GJ="$WORK/g.txt"; login "$TJ" 0999123456 correct-horse-1
INTAKE=$(sql "SELECT id FROM intakes WHERE is_active=1 ORDER BY id DESC LIMIT 1")
if [[ -z "$INTAKE" ]]; then sql "INSERT INTO intakes (name, opens_at) VALUES ('E2E intake', NOW())"; INTAKE=$(sql "SELECT id FROM intakes WHERE is_active=1 ORDER BY id DESC LIMIT 1"); fi
SCHOOL=$(sql "SELECT id FROM schools WHERE is_practice=0 ORDER BY id LIMIT 1")
app() { # n name lang whatsapp email
    sql "INSERT INTO applications (intake_id, reference, full_name, gender, form, track, school_id, phone, motivation, guardian_name, guardian_phone, guardian_whatsapp, guardian_email, guardian_language, guardian_relationship, guardian_consent_at, status)
         VALUES ($INTAKE, 'E2E-G$1', '$2', 'female', 1, 'general', ${SCHOOL:-NULL}, '+26598700000$1', 'I want to understand science and help my family.', 'Mayi Tembo', '+265887000111', $4, $5, '$3', 'Parent', NOW(), 'submitted')"
    sql "SELECT id FROM applications WHERE reference='E2E-G$1'"
}

echo "The application no longer needs the guardian present"
F=$(curl -s "$BASE/apply")
check "form says they need not be there"   "You do not need your parent or guardian with you" "$F"
check "  asks the guardian's language"     'name="guardian_language" value="ny"' "$F"
check "  asks for a WhatsApp number"       'name="guardian_whatsapp"' "$F"
check "  contact-book buttons available"    'data-contacts="tel"' "$F"
check "  contacts.js loaded"               "js/contacts.js" "$F"

echo "Admission copies the guardian details"
A1=$(app 1 "Tiwonge Tembo" ny "'+265997000111'" "'mayi.tembo@example.com'")
post "$TJ" "/studio/admissions/$A1" "/studio/admissions/$A1/decision" --data-urlencode decision=admit >/dev/null
SID=$(sql "SELECT id FROM users WHERE phone='+265987000001'")
check "  profile has WhatsApp and language" "+265997000111 ny mayi.tembo@example.com Parent" "$(sql "SELECT guardian_whatsapp, guardian_language, guardian_email, guardian_relationship FROM student_profiles WHERE user_id=$SID")"
TOK=$(sql "SELECT guardian_token FROM student_profiles WHERE user_id=$SID")
check "  a confirmation link token"        "24" "${#TOK}"
check "  admissions page points to it"     "/studio/guardians/$SID" "$(curl -s -b "$TJ" "$BASE/studio/admissions/$A1")"
check "  language shown on the application" "Chichewa" "$(curl -s -b "$TJ" "$BASE/studio/admissions/$A1")"

echo "Welcome message in the guardian's language"
K=$(curl -s -b "$TJ" "$BASE/studio/guardians/$SID")
check "Chichewa welcome"                   "Moni Mayi Tembo" "$K"
check "  names the child and form"         "Tiwonge Tembo (Fomu 1" "$K"
check "  asks for support"                 "foni kapena tabuleti" "$K"
check "  carries the link"                 "/g/$TOK" "$K"
check "  WhatsApp goes to the WhatsApp no." "https://wa.me/265997000111?text=Moni" "$K"
check "  SMS to the call number"           "sms:+265887000111?body=" "$K"
check "  email ready"                      "mailto:mayi.tembo@example.com?subject=SMART%20Circle%3A%20Tiwonge%20walandiridwa" "$K"
check "  call points in Chichewa"          "Dzidziwitseni" "$K"
check "  not yet confirmed"                "Not yet confirmed" "$K"
check "log the WhatsApp message"           "303" "$(post "$TJ" "/studio/guardians/$SID" "/studio/guardians/$SID/sent" --data-urlencode what=admission --data-urlencode channel=whatsapp | cut -c1-3)"
check "  in the contact log"               "[Guardian] Admission message sent" "$(sql "SELECT summary FROM contact_log WHERE person_id=$SID ORDER BY id DESC LIMIT 1")"
A2=$(app 2 "Kondwani Phiri" en NULL NULL)
post "$TJ" "/studio/admissions/$A2" "/studio/admissions/$A2/decision" --data-urlencode decision=admit >/dev/null
SID2=$(sql "SELECT id FROM users WHERE phone='+265987000002'")
K2=$(curl -s -b "$TJ" "$BASE/studio/guardians/$SID2")
check "English welcome"                    "Hello Mayi Tembo, this is" "$K2"
check "  no WhatsApp number: uses calls no." "https://wa.me/265887000111?text=Hello" "$K2"
refute "  no email button without email"   "mailto:" "$K2"

echo "The guardian's link (no login)"
G=$(curl -s -c "$GJ" -b "$GJ" "$BASE/g/$TOK")
check "page in Chichewa"                   "Kodi mukumudziwa" "$G"
check "  first name only"                  "Tiwonge walandiridwa" "$G"
refute "  no surname or school shown"      "Tembo" "$(grep -v '<title>' <<<"$G")"
check "  English on request"               "Do you know them" "$(curl -s -c "$GJ" -b "$GJ" "$BASE/g/$TOK?lang=en")"
check "unknown link is 404"                "404" "$(code "$BASE/g/000000000000000000000000")"
check "malformed link is 404"              "404" "$(code "$BASE/g/abc")"
check "guardian confirms"                  "Zikomo kwambiri" "$(post "$GJ" "/g/$TOK" "/g/$TOK" --data-urlencode answer=yes --data-urlencode lang=ny >/dev/null; cat "$WORK/last.html")"
check "  recorded as via link"             "link" "$(sql "SELECT guardian_confirmed_via FROM student_profiles WHERE user_id=$SID")"
check "  tutor notified"                   "Guardian confirmed: Tiwonge" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  shown as confirmed"               "Confirmed (from the link)" "$(curl -s -b "$TJ" "$BASE/studio/guardians/$SID")"
TOK2=$(sql "SELECT guardian_token FROM student_profiles WHERE user_id=$SID2")
post "$GJ" "/g/$TOK2" "/g/$TOK2" --data-urlencode answer=no --data-urlencode lang=en >/dev/null
check "a wrong number says no"             "Thank you for letting us know" "$(cat "$WORK/last.html")"
check "  flagged for the tutor"            "Check needed: a guardian does not know Kondwani" "$(curl -s -b "$TJ" "$BASE/notifications")"
check "  warning on the guardian page"     "said they do not know" "$(curl -s -b "$TJ" "$BASE/studio/guardians/$SID2")"
check "  guardians list shows it"          "Does not know the student" "$(curl -s -b "$TJ" "$BASE/studio/guardians")"
check "tutor corrects the number"          "303" "$(post "$TJ" "/studio/guardians/$SID2" "/studio/guardians/$SID2" --data-urlencode "guardian_name=Bambo Phiri" --data-urlencode guardian_relationship=Uncle --data-urlencode guardian_phone=0888000333 --data-urlencode guardian_whatsapp= --data-urlencode guardian_email=bambo@example.com --data-urlencode guardian_language=ny | cut -c1-3)"
check "  warning cleared, new link"        "1 1" "$(sql "SELECT guardian_declined_at IS NULL, guardian_token <> '$TOK2' FROM student_profiles WHERE user_id=$SID2")"
check "  old link no longer works"         "404" "$(code "$BASE/g/$TOK2")"
check "  bad email refused"                "does not look right" "$(post "$TJ" "/studio/guardians/$SID2" "/studio/guardians/$SID2" --data-urlencode "guardian_name=Bambo Phiri" --data-urlencode guardian_phone=0888000333 --data-urlencode guardian_email=nope --data-urlencode guardian_language=ny >/dev/null; cat "$WORK/last.html")"
check "confirmed on a call"                "303" "$(post "$TJ" "/studio/guardians/$SID2" "/studio/guardians/$SID2/confirm" | cut -c1-3)"
check "  recorded as via call"             "call" "$(sql "SELECT guardian_confirmed_via FROM student_profiles WHERE user_id=$SID2")"

echo "Monthly update"
LID=$(sql "SELECT id FROM lessons LIMIT 1")
[[ -n "$LID" ]] && sql "INSERT IGNORE INTO lesson_views (user_id, lesson_id) VALUES ($SID, $LID)"
M=$(curl -s -b "$TJ" "$BASE/studio/guardians/$SID")
check "monthly update in Chichewa"         "uthenga wachidule" "$M"
check "  with this month's name"           "mwezi wa $(php -r 'echo ["Januwale","Febuluwale","Malichi","Epulo","Meyi","Juni","Julayi","Ogasiti","Seputembala","Okutobala","Novembala","Disembala"][(int) date("n") - 1];')" "$M"
check "  not yet sent in the list"         "update not sent" "$(curl -s -b "$TJ" "$BASE/studio/guardians")"
check "log the monthly update"             "303" "$(post "$TJ" "/studio/guardians/$SID" "/studio/guardians/$SID/sent" --data-urlencode what=monthly --data-urlencode channel=whatsapp | cut -c1-3)"
check "  list shows it sent"               "update sent" "$(curl -s -b "$TJ" "$BASE/studio/guardians")"
check "visitors are sent to log in"        "303" "$(code "$BASE/studio/guardians")"

echo "Onboarding no longer waits for the guardian"
sql "UPDATE users SET password_hash='$(php -r 'echo password_hash("student-pass-1", PASSWORD_DEFAULT);')' WHERE id=$SID"
SJ="$WORK/s.txt"; login "$SJ" 0987000001 student-pass-1
OB=$(curl -s -b "$SJ" "$BASE/welcome/onboarding")
refute "no guardian checkbox in the pledge" 'name="guardian"' "$OB"
check "  explains the tutor tells them"    "The tutor will also tell your parent or guardian" "$OB"
check "students cannot see guardian tools" "403" "$(code -b "$SJ" "$BASE/studio/guardians/$SID")"

echo "Profile pictures"
php -r '$i = imagecreatetruecolor(800, 600); imagefill($i, 0, 0, imagecolorallocate($i, 30, 120, 200)); imagepng($i, $argv[1]);' "$WORK/me.png"
check "profile page"                       "Add a profile picture" "$(curl -s -b "$TJ" "$BASE/profile")"
T=$(token "$TJ" "$BASE/profile")
R=$(curl -s -o /dev/null -w '%{http_code}' -b "$TJ" -c "$TJ" -F "_token=$T" -F "photo=@$WORK/me.png;type=image/png" "$BASE/profile/photo")
check "upload a photo"                     "303" "$R"
TID=$(sql "SELECT id FROM users WHERE phone='+265999123456'")
curl -s -b "$TJ" -o "$WORK/av.jpg" -D "$WORK/avh.txt" "$BASE/avatars/$TID"
check "  served as a small JPEG"           "image/jpeg" "$(cat "$WORK/avh.txt")"
check "  made square, 256 px"              "256x256" "$(php -r '$s = getimagesize($argv[1]); echo $s[0] . "x" . $s[1];' "$WORK/av.jpg")"
check "  in the header"                    "/avatars/$TID?v=" "$(curl -s -b "$TJ" "$BASE/dashboard")"
check "  guests cannot see photos"         "303" "$(code "$BASE/avatars/$TID")"
echo "not a picture" > "$WORK/fake.png"
T=$(token "$TJ" "$BASE/profile")
check "  non-images refused"               "Use a JPG, PNG or WebP photo" "$(curl -s -b "$TJ" -c "$TJ" -F "_token=$T" -F "photo=@$WORK/fake.png;type=image/png" "$BASE/profile/photo")"
check "remove the photo"                   "303" "$(post "$TJ" /profile /profile/photo/remove | cut -c1-3)"
check "  gone"                             "404" "$(code -b "$TJ" "$BASE/avatars/$TID")"
check "  initials instead"                 "avatar--initials" "$(curl -s -b "$TJ" "$BASE/dashboard")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
