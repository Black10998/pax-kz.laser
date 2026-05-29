#!/usr/bin/env bash
# Copy or convert customer line model files into public/assets/lines/type_NN.svg (unchanged artwork).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-$ROOT/import/line-models}"
DEST="$ROOT/public/assets/lines"

mkdir -p "$SRC" "$DEST"

declare -A MAP=(
  ["model 21"]="21"
  ["model 22"]="22"
  ["model 23"]="23"
  ["model 24"]="24"
  ["model 25"]="25"
  ["model 26"]="26"
  ["model27"]="27"
  ["model 28"]="28"
  ["model 29"]="29"
  ["model 30"]="30"
  ["model31"]="31"
  ["model 32"]="32"
  ["model 33"]="33"
  ["model 34"]="34"
  ["model 35"]="35"
  ["model 36"]="36"
  ["model 37"]="37"
  ["model 38"]="38"
)

import_one() {
  local num="$1"
  local dest_file="$DEST/type_${num}.svg"
  local base
  for base in "model ${num}" "model${num}" "model_${num}" "type_${num}"; do
    if [[ -f "$SRC/${base}.svg" ]]; then
      cp -f "$SRC/${base}.svg" "$dest_file"
      echo "OK copied ${base}.svg -> type_${num}.svg"
      return 0
    fi
    if [[ -f "$SRC/${base}.ai" ]] && command -v inkscape >/dev/null 2>&1; then
      inkscape "$SRC/${base}.ai" --export-filename="$dest_file" --export-type=svg 2>/dev/null || \
        inkscape "$SRC/${base}.ai" -o "$dest_file" 2>/dev/null
      if [[ -f "$dest_file" ]]; then
        echo "OK converted ${base}.ai -> type_${num}.svg"
        return 0
      fi
    fi
  done
  return 1
}

count=0
for num in $(seq 21 38); do
  if import_one "$num"; then
    count=$((count + 1))
  else
    echo "SKIP type_${num} (no source in $SRC)" >&2
  fi
done

echo "Imported ${count} line model(s) into ${DEST}"
