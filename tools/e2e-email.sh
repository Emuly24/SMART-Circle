#!/usr/bin/env bash
# End-to-end test of email: the queue, sending after page visits, "Send now", test emails, retries,
# notifications by email (and turning them off), guardian emails and application decision emails.
#
# Needs an INSTALLED server whose secrets.php points mail at a test SMTP server, for example:
#   'mail' => ['host' => '127.0.0.1', 'port' => 2525, 'encryption' => 'none', 'username' => 'test', 'password' => 'test-only']
# and that server running: python3 tools/fake-smtp.py 2525 /tmp/mailbox
#   BASE_URL=... MYSQL="..." MAILBOX=/tmp/mailbox tools/e2e-email.sh
# Uses users +26599810000x and applications named "E2E Mail ...". Never point this at the real Zoho account.
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"; BOX="${MAILBOX:?}"
WORK="$(mktemp -d)"
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }
inbox() { { grep -l "X-Rcpt: $1" "$BOX"/*.eml 2>/dev/null || true; } | wc -l | tr -d ' '; }
body() { local f; f=$(grep -l "X-Rcpt: $1" "$BOX"/*.eml | tail -1); php -r '$m = file_get_contents($argv[1]); preg_match_all("/base64\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)/", $m, $p); echo implode("\n", array_map("base64_decode", $p[1]));' "$f"; }
subject() { grep -h "^Subject:" "$(grep -l "X-Rcpt: $1" "$BOX"/*.eml | tail -1)" | php -r 'echo iconv_mime_decode(trim(stream_get_contents(STDIN)), 0, "UTF-8");'; }
guardian_answers() { # token yes|no
    local jar="$WORK/g$1.txt" T
    T=$(token "$jar" "$BASE/g/$1")
    curl -s -o /dev/null -b "$jar" -c "$jar" --data-urlencode "_token=$T" --data-urlencode "answer=$2" "$BASE/g/$1"
}
cleanup() {
    sql "DELETE FROM users WHERE phone LIKE '+26599810000%'; DELETE FROM applications WHERE full_name LIKE 'E2E Mail %'"
    sql "UPDATE users SET email = NULL, email_notifications = 1 WHERE phone = '+265999123456'"
    rm -rf "$WORK"
}
trap cleanup EXIT

sql "DELETE FROM login_attempts; DELETE FROM mail_queue; UPDATE scheduled_jobs SET last_run_at = NULL WHERE name = 'mail'"
sql "DELETE FROM users WHERE phone LIKE '+26599810000%'; DELETE FROM applications WHERE full_name LIKE 'E2E Mail %'"
rm -f "${BOX:?}"/*.eml
HASH=$(php -r 'echo password_hash("mail-pass-1", PASSWORD_DEFAULT);')
student() {
    sql "INSERT INTO users (role, full_name, phone, gender, password_hash, email, email_notifications) VALUES ('student', '$2', '$1', 'female', '$HASH', $3, 1)"
    sql "INSERT INTO student_profiles (user_id, form, track, agreement_accepted_at, guardian_name, guardian_phone, guardian_email, guardian_language, guardian_token)
         SELECT id, 1, 'general', NOW(), 'Mayi Banda', '+265888000111', $4, '$5', '$6' FROM users WHERE phone='$1'"
}
student +265998100001 "Mail Wants" "'wants@example.com'" "'guardian.en@example.com'" en "aaaaaaaaaaaaaaaaaaaaaaa1"
student +265998100002 "Mail Quiet" "'quiet@example.com'" "'guardian.ny@example.com'" ny "aaaaaaaaaaaaaaaaaaaaaaa2"
student +265998100003 "Mail Noaddr" "NULL" "NULL" en "aaaaaaaaaaaaaaaaaaaaaaa3"
TJ="$WORK/t.txt"; SJ="$WORK/s.txt"
login "$TJ" 0999123456 correct-horse-1; login "$SJ" 0998100001 mail-pass-1
W1=$(sql "SELECT id FROM users WHERE phone='+265998100001'"); Q2=$(sql "SELECT id FROM users WHERE phone='+265998100002'"); N3=$(sql "SELECT id FROM users WHERE phone='+265998100003'")

echo "The admin's email page"
P=$(curl -s -b "$TJ" "$BASE/studio/mail")
check "email page"                         "Send waiting emails now" "$P"
refute "  email is switched on"            "Email is switched off" "$P"
check "settings links to it"               'href="/studio/mail"' "$(curl -s -b "$TJ" "$BASE/studio/settings")"
check "students kept out (403)"            "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$SJ" "$BASE/studio/mail")"
post "$TJ" /studio/mail /studio/mail/test --data-urlencode "to=nope" >/dev/null
check "bad test address refused"           "does not look right" "$(curl -s -b "$TJ" "$BASE/studio/mail")"
post "$TJ" /studio/mail /studio/mail/test --data-urlencode "to=tutor.test@example.com" >/dev/null
check "test email delivered"               "1" "$(inbox tutor.test@example.com)"
check "  with the right subject"           "SMART Circle test email" "$(subject tutor.test@example.com)"
check "  plain text and branded HTML"      "UNDERSTAND IT. APPLY IT." "$(body tutor.test@example.com)"
check "  marked sent"                      "sent" "$(sql "SELECT status FROM mail_queue WHERE to_email='tutor.test@example.com'")"
check "  sent from the contact address"    "From: SMART Circle <${CONTACT_EMAIL:-smartcircle@zohomail.com}>" "$(grep -h '^From:' "$(grep -l 'X-Rcpt: tutor.test@example.com' "$BOX"/*.eml | tail -1)")"
check "page lists it"                      "tutor.test@example.com" "$(curl -s -b "$TJ" "$BASE/studio/mail")"

echo "Notifications by email"
check "profile has email settings"         "Also send my notifications to this email" "$(curl -s -b "$SJ" "$BASE/profile")"
post "$TJ" /profile /profile/email --data-urlencode "email=Tutor.Me@Example.com" --data-urlencode email_notifications=1 >/dev/null
check "profile saves email (lowercased)"   "tutor.me@example.com 1" "$(sql "SELECT email, email_notifications FROM users WHERE phone='+265999123456'")"
check "bad email refused"                  "422" "$(post "$SJ" /profile /profile/email --data-urlencode "email=not-an-email")"
guardian_answers aaaaaaaaaaaaaaaaaaaaaaa1 yes
check "notification emailed (after the guardian's own page visit)" "sent" "$(sql "SELECT status FROM mail_queue WHERE to_email='tutor.me@example.com' AND template_key='notification'")"
check "  delivered"                        "1" "$(inbox tutor.me@example.com)"
check "  says what happened"               "Guardian confirmed: Mail" "$(body tutor.me@example.com)"
check "  links to the page"                "/studio/guardians" "$(body tutor.me@example.com)"
check "  explains how to stop them"        "turn them off in your profile" "$(body tutor.me@example.com)"
check "  job recorded"                     "Sent 1" "$(sql "SELECT last_result FROM scheduled_jobs WHERE name='mail'")"
post "$TJ" /profile /profile/email --data-urlencode "email=tutor.me@example.com" >/dev/null
check "turning it off is saved"            "0" "$(sql "SELECT email_notifications FROM users WHERE phone='+265999123456'")"
guardian_answers aaaaaaaaaaaaaaaaaaaaaaa2 no
check "  no email after turning it off"    "1" "$(sql "SELECT COUNT(*) FROM mail_queue WHERE to_email='tutor.me@example.com'")"
check "  the bell still works"             "Check needed: a guardian does not know Mail" "$(curl -s -b "$TJ" "$BASE/notifications")"

echo "Sending after ordinary page visits"
lazy() { sql "INSERT INTO mail_queue (to_email, to_name, subject, html_body, text_body, template_key) VALUES ('$1', '', 'E2E lazy', '<p>x</p>', 'x', 'test')"; }
lazy lazy1@example.com
sql "UPDATE scheduled_jobs SET last_run_at = NULL WHERE name = 'mail'"
curl -s -o /dev/null "$BASE/about"
check "a visitor's page visit sends it"    "1" "$(inbox lazy1@example.com)"
lazy lazy2@example.com
curl -s -o /dev/null "$BASE/about"
check "at most once every 2 minutes"       "0" "$(inbox lazy2@example.com)"
sql "UPDATE scheduled_jobs SET last_run_at = NOW() - INTERVAL 3 MINUTE WHERE name = 'mail'"
curl -s -o /dev/null "$BASE/library"
check "  then the next visit sends"        "1" "$(inbox lazy2@example.com)"

echo "Guardian emails"
P=$(curl -s -b "$TJ" "$BASE/studio/guardians/$W1")
check "guardian page: send by email"       "/studio/guardians/$W1/email" "$P"
refute "no email button without address"   "/studio/guardians/$N3/email" "$(curl -s -b "$TJ" "$BASE/studio/guardians/$N3")"
post "$TJ" "/studio/guardians/$W1" "/studio/guardians/$W1/email" --data-urlencode what=admission >/dev/null
post "$TJ" "/studio/guardians/$Q2" "/studio/guardians/$Q2/email" --data-urlencode what=monthly >/dev/null
check "queued"                             "Email queued" "$(curl -s -b "$TJ" "$BASE/studio/guardians/$Q2")"
check "logged as contact"                  "email" "$(sql "SELECT channel FROM contact_log WHERE person_id=$W1 AND summary LIKE '[Guardian] Admission%' ORDER BY id DESC LIMIT 1")"
post "$TJ" /studio/mail /studio/mail/send >/dev/null
check "admission message delivered"       "1" "$(inbox guardian.en@example.com)"
check "  with the confirmation link"      "/g/aaaaaaaaaaaaaaaaaaaaaaa1" "$(body guardian.en@example.com)"
check "monthly update delivered"          "1" "$(inbox guardian.ny@example.com)"
check "  subject names the child"         "SMART Circle: Mail" "$(subject guardian.ny@example.com)"
post "$TJ" "/studio/guardians/$N3" "/studio/guardians/$N3/email" --data-urlencode what=admission >/dev/null
check "no address: explained"             "has no email address" "$(curl -s -b "$TJ" "$BASE/studio/guardians/$N3")"

echo "Application decisions"
INTAKE=$(sql "SELECT id FROM intakes ORDER BY id DESC LIMIT 1")
sql "INSERT INTO applications (intake_id, reference, full_name, gender, form, track, phone, email, motivation, guardian_name, guardian_phone)
     VALUES ($INTAKE, 'SC-E2E-MAIL1', 'E2E Mail Applicant', 'male', 2, 'general', '+265998100009', 'applicant@example.com', 'I want to learn.', 'Bambo', '+265888000222'),
            ($INTAKE, 'SC-E2E-MAIL2', 'E2E Mail Nomail', 'male', 2, 'general', '+265998100008', NULL, 'I want to learn.', 'Bambo', '+265888000223')"
A1=$(sql "SELECT id FROM applications WHERE reference='SC-E2E-MAIL1'"); A2=$(sql "SELECT id FROM applications WHERE reference='SC-E2E-MAIL2'")
post "$TJ" "/studio/admissions/$A1" "/studio/admissions/$A1/decision" --data-urlencode decision=decline >/dev/null
post "$TJ" "/studio/admissions/$A2" "/studio/admissions/$A2/decision" --data-urlencode decision=decline >/dev/null
check "decision email queued"              "decision_declined" "$(sql "SELECT template_key FROM mail_queue WHERE to_email='applicant@example.com'")"
check "none without an address"            "1" "$(sql "SELECT COUNT(*) FROM mail_queue WHERE template_key LIKE 'decision_%'")"
post "$TJ" /studio/mail /studio/mail/send >/dev/null
B=$(body applicant@example.com)
check "applicant gets it"                  "apply again in the next intake" "$B"
check "  with the library"                 "/library" "$B"
check "  and their letter"                 "/letters/" "$B"

echo "When sending fails"
sql "INSERT INTO mail_queue (to_email, to_name, subject, html_body, text_body, template_key) VALUES ('broken@example.com', 'Line\nbreak', 'E2E broken', '<p>x</p>', 'x', 'test')"
post "$TJ" /studio/mail /studio/mail/send >/dev/null
check "failure kept for a retry"           "queued 1" "$(sql "SELECT status, attempts FROM mail_queue WHERE subject='E2E broken'")"
check "  retry is scheduled"               "1" "$(sql "SELECT next_try_at > NOW() FROM mail_queue WHERE subject='E2E broken'")"
check "  reason shown on the page"         "Bad address or subject" "$(curl -s -b "$TJ" "$BASE/studio/mail")"
sql "UPDATE mail_queue SET attempts = 4, next_try_at = NULL WHERE subject='E2E broken'"
post "$TJ" /studio/mail /studio/mail/send >/dev/null
check "after 5 tries: failed"              "failed" "$(sql "SELECT status FROM mail_queue WHERE subject='E2E broken'")"
post "$TJ" /studio/mail /studio/mail/retry >/dev/null
check "retry puts it back in the queue"    "queued 0" "$(sql "SELECT status, attempts FROM mail_queue WHERE subject='E2E broken'")"
sql "DELETE FROM mail_queue WHERE subject='E2E broken'"

echo; echo "Email: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
