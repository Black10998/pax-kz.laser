#!/usr/bin/env python3
"""Smoke: bundled Naruto eye SVG assets and manifest are present for catalog registration."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LINES = ROOT / "public/assets/lines"
MANIFEST = ROOT / "includes/bundled-line-manifest.php"

EXPECTED = {
	"type_102": "Sharingan (3 Tomoe)",
	"type_103": "Mangekyo Sharingan",
	"type_104": "Rinnegan",
	"type_105": "Mangekyo (Itachi)",
	"type_106": "Rinnegan (Tomoe)",
	"type_107": "Byakugan",
	"type_108": "Sage Mode",
	"type_109": "Tenseigan",
	"type_110": "Ketsuryugan",
	"type_111": "Eien no Mangekyo Sharingan",
}


def main() -> int:
	if not MANIFEST.is_file():
		print("FAIL missing bundled-line-manifest.php", file=sys.stderr)
		return 1

	manifest_text = MANIFEST.read_text(encoding="utf-8")
	for slug, label in EXPECTED.items():
		if slug not in manifest_text:
			print(f"FAIL manifest missing {slug}", file=sys.stderr)
			return 1
		if label not in manifest_text:
			print(f"FAIL manifest missing label for {slug}: {label}", file=sys.stderr)
			return 1

	found = 0
	for slug in EXPECTED:
		path = LINES / f"{slug}.svg"
		if not path.is_file():
			print(f"FAIL missing bundled SVG {path}", file=sys.stderr)
			return 1
		body = path.read_text(encoding="utf-8", errors="ignore")
		if 'viewBox="0 0 950 35"' not in body:
			print(f"FAIL {slug} missing 950x35 viewBox", file=sys.stderr)
			return 1
		if re.search(r"<text\b", body, re.I):
			print(f"FAIL {slug} still contains text labels", file=sys.stderr)
			return 1
		found += 1

	lib = (ROOT / "includes/class-pckz-line-library.php").read_text(encoding="utf-8")
	if "naruto_eye_slugs_with_assets" not in lib:
		print("FAIL line library missing naruto_eye_slugs_with_assets()", file=sys.stderr)
		return 1
	if "protected_bundled" not in lib:
		print("FAIL line library missing protected delete guard", file=sys.stderr)
		return 1

	preview = (ROOT / "includes/class-pckz-ledos-preview.php").read_text(encoding="utf-8")
	if "BUNDLED_LINE_TYPE_MAX = 111" not in preview:
		print("FAIL BUNDLED_LINE_TYPE_MAX is not 111", file=sys.stderr)
		return 1

	print(f"OK bundled-naruto-catalog-smoke: {found} models with manifest labels and clean SVG artboards")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
