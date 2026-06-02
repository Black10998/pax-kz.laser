#!/usr/bin/env bash
# Import 10 fire/line models from LightBurn export "vector fire model svg.ai" → type_72–type_81.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="${1:-$ROOT/import/vector-fire-models}"
SRC_FILE="${SRC_DIR}/vector fire model svg.ai"
DEST="$ROOT/public/assets/lines"
START=72
COUNT=10
END=81

if [[ ! -f "$SRC_FILE" ]]; then
	echo "Missing source file: ${SRC_FILE}" >&2
	echo "Place LightBurn export as: import/vector-fire-models/vector fire model svg.ai" >&2
	exit 1
fi

python3 "$ROOT/tools/convert-lightburn-ai-to-svg.py" "$SRC_FILE" -o "$DEST" --split "$COUNT" --start-type "$START" --expect "$COUNT"

imported=0
for num in $(seq "$START" "$END"); do
	if [[ -f "$DEST/type_${num}.svg" ]]; then
		imported=$((imported + 1))
	fi
done

echo "Imported ${imported} fire line model(s) (type_${START}–type_${END}) into ${DEST}"
echo "Next: set PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX to ${END} and bump plugin version."
