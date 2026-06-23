#!/usr/bin/env bash
set -euo pipefail
BASE="${1:-http://localhost:8088}"

echo "== render (problems.php) =="
RENDER=$(curl -fsS -X POST "$BASE/problems.php" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","qtext":"Find $a + $b","seed":1234}')
echo "$RENDER"
echo "$RENDER" | grep -q '"ok":true' || { echo "ERROR: render did not return \"ok\":true" >&2; exit 1; }
echo

echo "== score correct (scores.php) =="
SCORE=$(curl -fsS -X POST "$BASE/scores.php" \
  --data-urlencode 'qtype=number' \
  --data-urlencode 'control=$a = 5
$b = 7
$answer = $a + $b' \
  --data-urlencode 'seed=1234' \
  --data-urlencode 'answer=12')
echo "$SCORE"
echo "$SCORE" | grep -q '"scores":\[1\]' || { echo "ERROR: score did not return \"scores\":[1]" >&2; exit 1; }
echo

echo "== method guard problems.php (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/problems.php")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }

echo "== method guard scores.php (expect 405) =="
STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/scores.php")
echo "$STATUS"
[ "$STATUS" = "405" ] || { echo "ERROR: expected 405, got $STATUS" >&2; exit 1; }
