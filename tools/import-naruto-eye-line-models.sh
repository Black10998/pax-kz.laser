#!/usr/bin/env bash
# Import Naruto anime eye line models type_102–type_111 from reference-sheet.png (color trace).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/import/naruto-eye-models"
DEST="${ROOT}/public/assets/lines"
START=102
COUNT=10
END=111

if [[ ! -f "${SRC}/reference-sheet.png" && ! -f "${SRC}/reference-sheet.jpg" && ! -f "${SRC}/reference-sheet.jpeg" && ! -f "${SRC}/naruto-eye-reference-sheet.png" ]]; then
	echo "FAIL: Missing reference artwork." >&2
	echo "Commit your reference sheet PNG to:" >&2
	echo "  import/naruto-eye-models/reference-sheet.png" >&2
	echo "Then re-run: bash tools/import-naruto-eye-line-models.sh" >&2
	exit 1
fi

python3 "$ROOT/tools/import-naruto-eye-reference-sheet.py" "$SRC" -o "$DEST" --count "$COUNT"

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

php -r "
require '${ROOT}/tests/smoke-bootstrap.php';
require PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';
PCKZ_Line_Library::register_imported_customer_red_lines();
" 2>/dev/null || true

echo "Imported ${imported} Naruto eye line model(s) from reference sheet"
