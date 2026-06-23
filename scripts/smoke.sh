#!/usr/bin/env bash
set -euo pipefail
BASE="${1:-http://localhost:8088}"

echo "== render (problems.php) =="
curl -fsS -X POST "$BASE/problems.php" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","qtext":"Find $a + $b","seed":1234}'
echo

echo "== score correct (scores.php) =="
curl -fsS -X POST "$BASE/scores.php" \
  --data-urlencode 'qtype=number' \
  --data-urlencode 'control=$a = 5
$b = 7
$answer = $a + $b' \
  --data-urlencode 'seed=1234' \
  --data-urlencode 'answer=12'
echo

echo "== method guard (expect 405) =="
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/problems.php"
