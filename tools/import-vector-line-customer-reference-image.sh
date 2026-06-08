#!/usr/bin/env bash
# Import type_102–type_111 from reference sheet image (numbers stripped during import).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="${1:-$ROOT/import/vector-line-customer-red}"
DEST="$ROOT/public/assets/lines"
START=102
COUNT=10
END=111

for num in $(seq 102 121); do
	rm -f "$DEST/type_${num}.svg"
done

python3 "$ROOT/tools/import-reference-sheet-image.py" "$SRC_DIR" -o "$DEST" --count "$COUNT" --sheet-rows 15

imported=0
for num in $(seq "$START" "$END"); do
	if [[ -f "$DEST/type_${num}.svg" ]]; then
		imported=$((imported + 1))
	fi
done

if [[ "$imported" -ne "$COUNT" ]]; then
	echo "FAIL: expected ${COUNT} SVGs, got ${imported}" >&2
	exit 1
fi


echo "Imported ${imported} line model(s) from reference sheet image"
