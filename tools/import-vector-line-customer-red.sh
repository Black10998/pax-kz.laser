#!/usr/bin/env bash
# Import 10 matte-red customer line models from Illustrator/LightBurn export
# "svg for line vicor costumer.ai" → type_102–type_111.
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
		"svg for line vicor costumer.ai"
		"svg for line vector customer.ai"
		"svg for line vector costumer.ai"
	)
	local f
	for f in "${candidates[@]}"; do
		if [[ -f "${dir}/${f}" ]]; then
			echo "${dir}/${f}"
			return 0
		fi
	done
	if [[ -f "$ROOT/svg for line vicor costumer.ai" ]]; then
		echo "$ROOT/svg for line vicor costumer.ai"
		return 0
	fi
	return 1
}

SRC_FILE=""
if SRC_FILE="$(find_src "$SRC_DIR")"; then
	:
else
	echo "Missing source file." >&2
	echo "Copy your Illustrator export to:" >&2
	echo "  ${SRC_DIR}/svg for line vicor costumer.ai" >&2
	exit 1
fi

python3 "$ROOT/tools/convert-lightburn-ai-to-svg.py" \
	"$SRC_FILE" \
	-o "$DEST" \
	--split "$COUNT" \
	--start-type "$START" \
	--expect "$COUNT" \
	--fill-color "$FILL_COLOR"

imported=0
for num in $(seq "$START" "$END"); do
	if [[ -f "$DEST/type_${num}.svg" ]]; then
		imported=$((imported + 1))
	fi
done

echo "Imported ${imported} customer red line model(s) (type_${START}–type_${END}) from $(basename "$SRC_FILE")"
echo "Set PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX to ${END} in includes/class-pckz-ledos-preview.php"
