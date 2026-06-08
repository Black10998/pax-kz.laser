#!/usr/bin/env bash
# Import 10 matte-red customer lines from LightBurn svg.lbrn2 → type_102–type_111.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="${1:-$ROOT/import/vector-line-customer-red}"
DEST="$ROOT/public/assets/lines"
START=102
COUNT=10
END=111
FILL_COLOR="#B22222"

find_src() {
	local dir="$1"
	local candidates=(
		"svg.lbrn2"
		"svg for line vicor costumer.lbrn2"
	)
	local f
	for f in "${candidates[@]}"; do
		if [[ -f "${dir}/${f}" ]]; then
			echo "${dir}/${f}"
			return 0
		fi
	done
	if [[ -f "$ROOT/svg.lbrn2" ]]; then
		echo "$ROOT/svg.lbrn2"
		return 0
	fi
	return 1
}

SRC_FILE=""
if SRC_FILE="$(find_src "$SRC_DIR")"; then
	:
else
	echo "Missing source file." >&2
	echo "Copy LightBurn export to:" >&2
	echo "  ${SRC_DIR}/svg.lbrn2" >&2
	exit 1
fi

# Remove stale incorrect models before import.
for num in $(seq 102 121); do
	rm -f "$DEST/type_${num}.svg"
done

python3 "$ROOT/tools/import-lbrn2-line-models.py" \
	"$SRC_FILE" \
	-o "$DEST" \
	--start-type "$START" \
	--count "$COUNT" \
	--fill-color "$FILL_COLOR"

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

echo "Imported ${imported} line model(s) from $(basename "$SRC_FILE")"
