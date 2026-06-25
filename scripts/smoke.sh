#!/usr/bin/env bash
set -euo pipefail
BASE="${1:-http://localhost:8088}"

echo "== render (/render) =="
RENDER=$(curl -fsS -X POST "$BASE/render" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","qtext":"Find $a + $b","seed":1234}')
echo "$RENDER"
echo "$RENDER" | grep -q '"ok":true' || { echo "ERROR: render did not return \"ok\":true" >&2; exit 1; }
echo

echo "== score correct (/score) =="
SCORE=$(curl -fsS -X POST "$BASE/score" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","seed":1234,"answer":"12"}')
echo "$SCORE"
echo "$SCORE" | grep -q '"scores":\[1\]' || { echo "ERROR: score did not return \"scores\":[1]" >&2; exit 1; }
echo

echo "== method guard /render (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/render")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }

echo "== method guard /score (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/score")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }
