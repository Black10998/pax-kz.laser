#!/usr/bin/env bash
# Font → OpenType → SVG path fragment → PHP export → LBRN2 (Great Vibes + Russo One).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
GV_TTF="${GV_TTF:-https://fonts.gstatic.com/s/greatvibes/v21/RWmMoKWR9v4ksMfaWd_JN-XC.ttf}"
# Optional second font (set RU_TTF to a live .ttf URL); skipped when unset or invalid.
RU_TTF="${RU_TTF:-}"
NODE_DIR="$ROOT/tests/node"
if [[ ! -d "$NODE_DIR/node_modules/opentype.js" ]]; then
  (cd "$NODE_DIR" && npm install opentype.js@1.3.4 --no-save >/dev/null 2>&1)
fi
export NODE_PATH="$NODE_DIR/node_modules${NODE_PATH:+:$NODE_PATH}"
run_font() {
  local name="$1" url="$2"
  local fragment
  fragment="$(node "$ROOT/tests/helpers/generate-text-plate-path-fragment.mjs" "$url")"
  if [[ -z "$fragment" || "$fragment" != *"<path d="* ]]; then
    echo "FAIL $name: empty fragment" >&2
    exit 1
  fi
  FRAGMENT="$fragment" FONT_NAME="$name" php "$ROOT/tests/helpers/font-lbrn2-export-e2e.php"
}
run_font "great-vibes" "$GV_TTF"
if [[ -n "$RU_TTF" ]]; then
  run_font "russo-one" "$RU_TTF"
fi
echo "OK font-lbrn2-export-e2e: OpenType paths → LBRN2 (Great Vibes script curves + Q/C commands)"
