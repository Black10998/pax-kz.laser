#!/usr/bin/env bash
# Copy NEU icon modele SVGs into public/images/icons/bundled/ and regenerate manifest.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/import/icon-models-neu"
DST="${ROOT}/public/images/icons/bundled"
MANIFEST="${ROOT}/includes/bundled-premium-icons.php"

if [[ ! -d "$SRC" ]]; then
  echo "Extract NEU icon modele.zip to import/icon-models-neu first." >&2
  exit 1
fi

mkdir -p "$DST"
python3 << PY
import pathlib, shutil
root = pathlib.Path("${ROOT}")
src = root / "import/icon-models-neu"
dst = root / "public/images/icons/bundled"
lines = ["<?php", "/** Auto-generated from NEU icon modele.zip */", "defined( 'ABSPATH' ) || exit;", "return array("]
for f in sorted(src.glob("*.svg")):
    slug = "icon_" + f.stem
    shutil.copy2(f, dst / f"{slug}.svg")
    lines.append(f"\t'{slug}' => 'Symbol {f.stem}',")
lines.append(");")
lines.append("")
(root / "includes/bundled-premium-icons.php").write_text("\\n".join(lines), encoding="utf-8")
print("OK", len(list(dst.glob("*.svg"))), "icons")
PY
