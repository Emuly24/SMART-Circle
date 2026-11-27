#!/usr/bin/env bash
# End-to-end test of invitation letters (school teacher, volunteer tutor) and brand-styled written text.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-letters.sh
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"; $MYSQL -N -e "DELETE FROM issued_documents WHERE subject_type = '"'"'invitation'"'"' AND data LIKE '"'"'%E2E Invite%'"'"'"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
refute() { if [[ "$3" != *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (did not expect '$2')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts"
TJ="$WORK/t.txt"; login "$TJ" 0999123456 correct-horse-1

echo "Invitation letters"
check "page opens"                         "Invitation letters" "$(curl -s -b "$TJ" "$BASE/studio/invitations")"
check "partners page links to it"          "/studio/invitations?type=teacher_invitation" "$(curl -s -b "$TJ" "$BASE/studio/partners")"
check "team page links to it"              "/studio/invitations?type=tutor_invitation" "$(curl -s -b "$TJ" "$BASE/studio/team")"
check "guests kept out"                    "303" "$(code "$BASE/studio/invitations")"
check "teacher needs a school"             "422" "$(post "$TJ" /studio/invitations /studio/invitations --data-urlencode type=teacher_invitation --data-urlencode "full_name=E2E Invite Phiri" --data-urlencode "subjects[]=biology" | cut -c1-3)"
check "  explained"                        "Write the name of their school" "$(cat "$WORK/last.html")"
R=$(post "$TJ" /studio/invitations /studio/invitations --data-urlencode type=teacher_invitation --data-urlencode title=Mrs --data-urlencode "full_name=Grace E2E Invite" \
    --data-urlencode "school=Likuni Girls Secondary" --data-urlencode district=Lilongwe --data-urlencode "subjects[]=biology" --data-urlencode "subjects[]=chemistry" --data-urlencode "note=We met at the science fair in Lilongwe.")
check "teacher letter written"             "303 $BASE/letters/L" "$R"
TL=${R##*/letters/}
L=$(curl -s "$BASE/letters/$TL")
check "anyone with the link can read it"   "Invitation to become a SMART Circle partner teacher" "$L"
check "  formal greeting"                  "Dear Mrs Invite," "$L"
check "  addressed to the school"          "Likuni Girls Secondary, Lilongwe" "$L"
check "  why we need you"                  "Why we need you" "$L"
check "  honesty with schools"             "Honesty with schools" "$L"
check "  what you gain"                    "What you gain" "$L"
check "  free support for their students"  "Free extra support for your own students" "$L"
check "  certificate of contribution"      "verifiable certificate of contribution" "$L"
check "  their subjects"                   "Chemistry, Biology" "$L"
check "  personal note"                    "We met at the science fair" "$L"
check "  how to join"                      "How to join" "$L"
check "  verifiable"                       "/verify/$TL" "$L"
check "verify page confirms it"            "Invitation to become a SMART Circle partner teacher" "$(curl -s "$BASE/verify/$TL")"
R=$(post "$TJ" /studio/invitations /studio/invitations --data-urlencode type=tutor_invitation --data-urlencode "full_name=Kondwani E2E Invite" --data-urlencode "subjects[]=physics")
UL=${R##*/letters/}
L=$(curl -s "$BASE/letters/$UL")
check "tutor letter written"               "Invitation to join the SMART Circle Team" "$L"
check "  first-name greeting"              "Dear Kondwani," "$L"
check "  why we need you"                  "More students than one tutor can serve" "$L"
check "  what they would do"               "What you would do" "$L"
check "  what you gain"                    "A reference from the founder" "$L"
check "  principles first"                 "agrees to the SMART Circle philosophy and principles" "$L"
check "listed on the page"                 "$UL" "$(curl -s -b "$TJ" "$BASE/studio/invitations")"
check "other letters stay private"         "404" "$(code "$BASE/letters/$(sql "SELECT code FROM issued_documents WHERE subject_type='user' ORDER BY id LIMIT 1")")"

echo "Brand styling of written text"
C=$(curl -s "$BASE/assets/css/app.css"); P=$(curl -s "$BASE/assets/css/print.css")
check "site prose: blue headings, green bar" ".prose h2::after" "$C"
check "site prose: bold in brand blue"     ".prose strong" "$C"
check "letters: brand headings"            ".letter h2::after" "$P"
check "letters: green list markers"        ".letter li::marker" "$P"
check "letters: colours kept in print"     "print-color-adjust: exact" "$P"
check "About story uses the prose style"   'class="story__text prose"' "$(curl -s "$BASE/about")"

echo "Sharing"
H=$(curl -s "$BASE/")
check "home page has a preview title"      'property="og:title"' "$H"
check "  and the share card"               "/assets/brand/share-card.png" "$H"
check "  large card on X"                  'twitter:card" content="summary_large_image"' "$H"
check "  share buttons"                    "Share SMART Circle" "$H"
check "  WhatsApp share link"              "https://wa.me/?text=" "$H"
check "share card is served"               "image/png" "$(curl -s -D - -o /dev/null "$BASE/assets/brand/share-card.png")"
check "About can be shared"                "Share our story" "$(curl -s "$BASE/about")"
check "  with its own description"         "The founder&#039;s story" "$(curl -s "$BASE/about")"
check "library can be shared"              "Share the free library" "$(curl -s "$BASE/library")"

echo; echo "Letters: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
