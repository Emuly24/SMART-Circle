#!/usr/bin/env bash
# End-to-end test of the teaching tools: the board's Type tool and page pictures, centred equations
# and calculation steps in notes, picture positions, and the drawing pad.
#
# Needs an INSTALLED server (tutor 0999123456 / correct-horse-1) and a mysql client:
#   BASE_URL=http://127.0.0.1:8098 MYSQL="mariadb -h127.0.0.1 -P3307 -usc -pscpw sc_e2e" tools/e2e-teach.sh
set -euo pipefail

BASE="${BASE_URL:?}"; MYSQL="${MYSQL:?}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
check() { if [[ "$3" == *"$2"* ]]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected '$2', got '${3:0:300}')"; fi; }
token() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="//;s/"//'; }
sql() { $MYSQL -N -e "$1" | tr '\t' ' '; }
login() { local T; T=$(token "$1" "$BASE/login"); curl -s -o /dev/null -b "$1" -c "$1" --data-urlencode "_token=$T" --data-urlencode "phone=$2" --data-urlencode "password=$3" "$BASE/login"; }
post() { local jar=$1 page=$2 action=$3 T; shift 3; T=$(token "$jar" "$BASE$page"); curl -s -o "$WORK/last.html" -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" --data-urlencode "_token=$T" "$@" "$BASE$action"; }

sql "DELETE FROM login_attempts; DELETE FROM notes WHERE title LIKE 'E2E teach %'"
TJ="$WORK/t.txt"; login "$TJ" 0999123456 correct-horse-1

echo "The board"
L=$(curl -s -b "$TJ" "$BASE/studio/lab")
check "Type tool on the board"             'data-tool="text"' "$L"
check "  text box with the notation help"  'id="text-input"' "$L"
check "  typed lines centred by default"   'id="text-centred" checked' "$L"
check "  save a page as a picture"         'id="snapshot"' "$L"
CORE=$(curl -s "$BASE/assets/js/board/board-core.js")
check "  text events replay"               "case 'text':" "$CORE"
check "  equation notation"                "export function textRuns" "$CORE"
check "  eraser handles text"              "stroke.type === 'text'" "$CORE"
check "  undo covers text"                 "this.history.push(item.id)" "$(curl -s "$BASE/assets/js/board/board-input.js")"

echo "Equations and calculation steps in notes"
TOPIC=$(sql "SELECT id FROM topics WHERE form=3 AND subject='physics' AND is_active=1 LIMIT 1")
post "$TJ" /studio/notes/new /studio/notes --data-urlencode kind=notes --data-urlencode "title=E2E teach motion" --data-urlencode form=3 --data-urlencode subject=physics --data-urlencode topic_id=$TOPIC >/dev/null
NID=$(sql "SELECT id FROM notes WHERE title='E2E teach motion'")
post "$TJ" "/studio/notes/$NID" "/studio/notes/$NID/text" --data-urlencode "body=A car starts from rest.
= v = u + at
= v = 0 + 9.8 * 2
= v = 19.6 m/s
So the speed is v >= 0." >/dev/null
E=$(curl -s -b "$TJ" "$BASE/studio/notes/$NID")
check "steps grouped and centred"          '<div class="note-calc"><p class="note-eq">v = u + at</p>' "$E"
check "  * is a times sign in equations"   "9.8 × 2" "$E"
check "  symbols"                          "v ≥ 0" "$E"
check "  help explains the notation"       "= v = u + at</code> an equation" "$E"

echo "Pictures: position and the drawing pad"
check "drawing pad offered"                "js-drawpad-open" "$E"
check "  drawpad.js loaded"                "js/drawpad.js" "$E"
check "  position when uploading"          '<select name="align">' "$E"
php -r '$i = imagecreatetruecolor(400, 250); imagefill($i, 0, 0, imagecolorallocate($i, 255, 255, 255)); imageline($i, 10, 10, 390, 240, imagecolorallocate($i, 20, 33, 61)); imagepng($i, $argv[1]);' "$WORK/drawing.png"
T=$(token "$TJ" "$BASE/studio/notes/$NID")
check "add a drawing on the left"          "303" "$(curl -s -o /dev/null -w '%{http_code}' -b "$TJ" -c "$TJ" -F "_token=$T" -F "image=@$WORK/drawing.png;type=image/png" -F "caption=Velocity-time graph" -F align=left "$BASE/studio/notes/$NID/image")"
BID=$(sql "SELECT id FROM note_blocks WHERE note_id=$NID AND type='image'")
check "  stored on the left"               "left" "$(sql "SELECT align FROM note_blocks WHERE id=$BID")"
check "  shown on the left"                "note-figure--left" "$(curl -s -b "$TJ" "$BASE/studio/notes/$NID")"
check "move it to the right"               "303" "$(post "$TJ" "/studio/notes/$NID" "/studio/notes/$NID/blocks/$BID/align" --data-urlencode align=right | cut -c1-3)"
check "  now on the right"                 "right" "$(sql "SELECT align FROM note_blocks WHERE id=$BID")"
post "$TJ" "/studio/notes/$NID" "/studio/notes/$NID/blocks/$BID/align" --data-urlencode align=sideways >/dev/null
check "  nonsense positions refused"       "right" "$(sql "SELECT align FROM note_blocks WHERE id=$BID")"
TXT=$(sql "SELECT id FROM note_blocks WHERE note_id=$NID AND type='text'")
post "$TJ" "/studio/notes/$NID" "/studio/notes/$NID/blocks/$TXT/align" --data-urlencode align=left >/dev/null
check "  only pictures have positions"     "center" "$(sql "SELECT align FROM note_blocks WHERE id=$TXT")"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
