#!/usr/bin/env bash
# Import Naruto anime eye line models type_102–type_111 from LightBurn LBT thumbnail.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/import/naruto-eye-models"
LBT="${SRC}/10 line.vector naroto aye.lbt"
REF="${SRC}/reference-sheet.png"
DEST="${ROOT}/public/assets/lines"
START=102
COUNT=10
END=111

if [[ -f "$LBT" ]]; then
	if [[ ! -f "$REF" || "$LBT" -nt "$REF" ]]; then
		python3 "$ROOT/tools/extract-lightburn-thumbnail.py" "$LBT" "$REF" --force
	fi
fi

if [[ ! -f "${SRC}/reference-sheet.png" && ! -f "${SRC}/reference-sheet.jpg" && ! -f "${SRC}/reference-sheet.jpeg" && ! -f "${SRC}/naruto-eye-reference-sheet.png" ]]; then
	echo "FAIL: Missing reference artwork." >&2
	echo "Provide the LightBurn source or a reference sheet PNG:" >&2
	echo "  import/naruto-eye-models/10 line.vector naroto aye.lbt" >&2
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
