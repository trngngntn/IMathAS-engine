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
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","seed":1234,"answers":[{"id":"qn0","value":"12"}]}')
echo "$SCORE"
echo "$SCORE" | grep -q '"id":"qn0","raw":1' || { echo "ERROR: score did not return a correct qn0 part" >&2; exit 1; }
echo

echo "== score multipart by flat part ids qn0/qn1 (/score) =="
MULTI=$(curl -fsS -X POST "$BASE/score" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"multipart","control":"$anstypes = array(\"number\",\"number\")\n$answer[0] = 3\n$answer[1] = 4","seed":1234,"answers":[{"id":"qn0","value":"3"},{"id":"qn1","value":"99"}]}')
echo "$MULTI"
echo "$MULTI" | grep -q '"id":"qn1","raw":0' || { echo "ERROR: multipart score did not grade qn1 wrong" >&2; exit 1; }
echo

echo "== method guard /render (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/render")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }

echo "== method guard /score (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/score")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }
