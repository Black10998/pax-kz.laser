#!/usr/bin/env bash
# Import Naruto anime eye line models type_102–type_111.
# Priority: separate source SVGs → LightBurn LBT thumbnail → reference sheet PNG.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/import/naruto-eye-models"
SOURCES="${SRC}/sources"
LBT="${SRC}/10 line.vector naroto aye.lbt"
REF="${SRC}/reference-sheet.png"
DEST="${ROOT}/public/assets/lines"
START=102
COUNT=10
END=111

source_svg_count=0
if [[ -d "$SOURCES" ]]; then
	source_svg_count="$(find "$SOURCES" -maxdepth 1 -type f -name '*.svg' 2>/dev/null | wc -l | tr -d ' ')"
fi

if [[ "$source_svg_count" -ge "$COUNT" ]]; then
	echo "Importing ${COUNT} Naruto eye models from separate source SVGs in ${SOURCES}"
	python3 "$ROOT/tools/import-naruto-eye-separate-svgs.py" "$SOURCES" -o "$DEST" --count "$COUNT"
else
	if [[ -f "$LBT" ]]; then
		if [[ ! -f "$REF" || "$LBT" -nt "$REF" ]]; then
			python3 "$ROOT/tools/extract-lightburn-thumbnail.py" "$LBT" "$REF" --force
		fi
	fi

	if [[ ! -f "${SRC}/reference-sheet.png" && ! -f "${SRC}/reference-sheet.jpg" && ! -f "${SRC}/reference-sheet.jpeg" && ! -f "${SRC}/naruto-eye-reference-sheet.png" ]]; then
		echo "FAIL: Missing reference artwork." >&2
		echo "Provide 10 separate SVG files, the LightBurn source, or a reference sheet PNG:" >&2
		echo "  import/naruto-eye-models/sources/*.svg  (preferred)" >&2
		echo "  import/naruto-eye-models/10 line.vector naroto aye.lbt" >&2
		echo "  import/naruto-eye-models/reference-sheet.png" >&2
		echo "Then re-run: bash tools/import-naruto-eye-line-models.sh" >&2
		exit 1
	fi

	python3 "$ROOT/tools/import-naruto-eye-reference-sheet.py" "$SRC" -o "$DEST" --count "$COUNT"
fi

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
