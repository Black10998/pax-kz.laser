#!/usr/bin/env python3
"""
Import Naruto anime eye line models type_102–type_111 from individual source SVG files.

Expected layout (10 files, numeric prefix = sheet order):
  import/naruto-eye-models/sources/01-sharingan.svg
  import/naruto-eye-models/sources/02-mangekyo-sharingan.svg
  ...

Text labels, filename captions, and number prefixes are stripped from the artwork.
Colors and geometry are preserved; output is normalized to 950×35.
"""

from __future__ import annotations

import argparse
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

# Reuse normalization helpers from the reference-sheet importer.
import importlib.util

_REF_SCRIPT = Path(__file__).resolve().parent / "import-naruto-eye-reference-sheet.py"
_spec = importlib.util.spec_from_file_location("naruto_ref_sheet", _REF_SCRIPT)
if _spec is None or _spec.loader is None:
	raise RuntimeError(f"Cannot load reference-sheet importer: {_REF_SCRIPT}")
_ref = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_ref)

MODEL_COUNT = _ref.MODEL_COUNT
START_TYPE = _ref.START_TYPE
collect_drawables = _ref.collect_drawables
normalize_traced_svg = _ref.normalize_traced_svg
path_bbox = _ref.path_bbox

SVG_NS = "http://www.w3.org/2000/svg"
ET.register_namespace("", SVG_NS)

LABEL_TEXT_RE = re.compile(
	r"(sharingan|mangekyo|rinnegan|byakugan|sage|tenseigan|ketsuryugan|eien|tomoe|itachi|\.svg)",
	re.I,
)
NUMBER_PREFIX_RE = re.compile(r"^\s*\d{1,2}\s*[\.\)]?\s*")


def sort_source_files(src_dir: Path) -> list[Path]:
	files = sorted(src_dir.glob("*.svg"))
	if len(files) < MODEL_COUNT:
		raise FileNotFoundError(
			f"Expected at least {MODEL_COUNT} SVG files in {src_dir}, found {len(files)}"
		)
	def order_key(path: Path) -> tuple[int, str]:
		m = re.match(r"^(\d{1,2})", path.stem)
		return (int(m.group(1)) if m else 999, path.name.lower())

	return sorted(files, key=order_key)[:MODEL_COUNT]


def is_label_element(el: ET.Element) -> bool:
	tag = el.tag.split("}")[-1] if "}" in el.tag else el.tag
	if tag not in ("text", "tspan"):
		return False
	text = "".join(el.itertext()).strip()
	if not text:
		return True
	if NUMBER_PREFIX_RE.match(text):
		return True
	if LABEL_TEXT_RE.search(text):
		return True
	if len(text) > 24 and " " in text:
		return True
	return False


def strip_labels(root: ET.Element) -> None:
	for el in list(root.iter()):
		if is_label_element(el):
			parent = None
			for candidate in root.iter():
				for child in list(candidate):
					if child is el:
						parent = candidate
						break
				if parent is not None:
					break
			if parent is not None:
				parent.remove(el)


def crop_label_region(root: ET.Element) -> None:
	"""Drop paths whose bbox sits in the typical bottom filename caption band."""
	drawables = collect_drawables(root)
	if not drawables:
		return
	bboxes = [path_bbox(d) for d, _, _, _ in drawables]
	bboxes = [b for b in bboxes if b]
	if not bboxes:
		return
	y0 = min(b[1] for b in bboxes)
	y1 = max(b[3] for b in bboxes)
	height = y1 - y0
	if height <= 0:
		return
	cutoff = y0 + height * 0.72
	for el in list(root.iter()):
		tag = el.tag.split("}")[-1] if "}" in el.tag else el.tag
		if tag != "path":
			continue
		d = el.get("d") or ""
		bb = path_bbox(d)
		if not bb:
			continue
		if bb[1] >= cutoff:
			for parent in root.iter():
				for child in list(parent):
					if child is el:
						parent.remove(el)
						break


def prepare_source_svg(src: Path, tmp_dir: Path, idx: int) -> Path:
	tree = ET.parse(src)
	root = tree.getroot()
	strip_labels(root)
	crop_label_region(root)
	out = tmp_dir / f"source_{idx:02d}.svg"
	tree.write(out, encoding="utf-8", xml_declaration=True)
	return out


def main() -> int:
	parser = argparse.ArgumentParser(description="Import separate Naruto eye SVGs → type_102–111")
	parser.add_argument(
		"src_dir",
		type=Path,
		nargs="?",
		default=Path(__file__).resolve().parent.parent / "import/naruto-eye-models/sources",
	)
	parser.add_argument(
		"-o",
		"--output",
		type=Path,
		default=Path(__file__).resolve().parent.parent / "public/assets/lines",
	)
	parser.add_argument("--start-type", type=int, default=START_TYPE)
	parser.add_argument("--count", type=int, default=MODEL_COUNT)
	args = parser.parse_args()

	try:
		sources = sort_source_files(args.src_dir)
	except FileNotFoundError as exc:
		print(exc, file=sys.stderr)
		return 1

	import tempfile

	tmp_dir = Path(tempfile.mkdtemp(prefix="naruto-eye-separate-"))
	written = 0
	for idx, src in enumerate(sources):
		type_num = args.start_type + idx
		dest = args.output / f"type_{type_num}.svg"
		prepared = prepare_source_svg(src, tmp_dir, idx)
		normalize_traced_svg(prepared, dest)
		written += 1
		print(f"  type_{type_num}.svg  <=  {src.name}")

	print(f"Imported {written} Naruto eye line model(s) from separate SVG sources")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
