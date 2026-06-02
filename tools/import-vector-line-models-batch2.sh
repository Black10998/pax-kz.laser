#!/usr/bin/env bash
# Import 10 decorative line models (batch 2) → type_82–type_91.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="${1:-$ROOT/import/vector-line-models-2}"
DEST="$ROOT/public/assets/lines"
START=82
COUNT=10
END=91

find_src() {
	local dir="$1"
	local candidates=(
		"vector line models 2.ai"
		"vector line models svg.ai"
		"vector line model svg 2.ai"
		"vector line model svg.ai"
	)
	local f
	for f in "${candidates[@]}"; do
		if [[ -f "${dir}/${f}" ]]; then
			echo "${dir}/${f}"
			return 0
		fi
	done
	if [[ -f "$ROOT/vector line models 2.ai" ]]; then
		echo "$ROOT/vector line models 2.ai"
		return 0
	fi
	return 1
}

SRC_FILE=""
if SRC_FILE="$(find_src "$SRC_DIR")"; then
	:
else
	echo "Missing source file in ${SRC_DIR}/" >&2
	echo "Expected one of: vector line models 2.ai, vector line models svg.ai" >&2
	exit 1
fi

python3 "$ROOT/tools/convert-lightburn-ai-to-svg.py" "$SRC_FILE" -o "$DEST" --split "$COUNT" --start-type "$START" --expect "$COUNT"

imported=0
for num in $(seq "$START" "$END"); do
	if [[ -f "$DEST/type_${num}.svg" ]]; then
		imported=$((imported + 1))
	fi
done

echo "Imported ${imported} line model(s) (type_${START}–type_${END}) from $(basename "$SRC_FILE")"
