#!/usr/bin/env bash
# Import NEU modelen archive (types 39–71) into public/assets/lines/.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-$ROOT/import/line-models-neu}"
ARCHIVE="$ROOT/import/line-models"
DEST="$ROOT/public/assets/lines"

mkdir -p "$SRC" "$ARCHIVE" "$DEST"

cp -f "$SRC"/*.ai "$ARCHIVE/" 2>/dev/null || true
cp -f "$SRC"/*.AI "$ARCHIVE/" 2>/dev/null || true

python3 "$ROOT/tools/convert-lightburn-ai-to-svg.py" "$SRC" -o "$DEST" --expect 33

count=0
for num in $(seq 39 71); do
	if [[ -f "$DEST/type_${num}.svg" ]]; then
		count=$((count + 1))
	fi
done

echo "Imported ${count} new line model(s) (type_39–type_71) into ${DEST}"
