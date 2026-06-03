#!/usr/bin/env python3
"""
Convert a single line ornament source file to canonical 950×35 SVG (PCKZ line library).

Supports: .lbrn2, .ai, .eps, .svg (native paths; no redesign).
Optional: .dxf, .pdf via Inkscape when installed.
"""

from __future__ import annotations

import argparse
import importlib.util
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

CANONICAL_VIEWBOX = "0 0 950 35"


def load_converter():
	root = Path(__file__).resolve().parent
	spec = importlib.util.spec_from_file_location(
		"pckz_convert_ai", root / "convert-lightburn-ai-to-svg.py"
	)
	mod = importlib.util.module_from_spec(spec)
	assert spec.loader
	spec.loader.exec_module(mod)
	return mod


def load_lbrn2_helpers():
	root = Path(__file__).resolve().parent
	spec = importlib.util.spec_from_file_location(
		"pckz_lbrn2", root / "import-lbrn2-line-models.py"
	)
	mod = importlib.util.module_from_spec(spec)
	assert spec.loader
	spec.loader.exec_module(mod)
	return mod


def parse_path_d(d: str) -> list[tuple]:
	tokens = re.findall(
		r"[MmLlHhVvCcSsQqTtAaZz]|"
		r"[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?",
		d,
	)
	ops: list[tuple] = []
	i = 0
	cx = cy = 0.0

	def read_num() -> float:
		nonlocal i
		v = float(tokens[i])
		i += 1
		return v

	while i < len(tokens):
		cmd = tokens[i]
		if cmd in "MmLlHhVvCcZz":
			i += 1
		else:
			cmd = "L"
		rel = cmd.islower()
		c = cmd.upper()
		if c == "Z":
			ops.append(("Z", cx, cy))
			continue
		if c == "M":
			x, y = read_num(), read_num()
			if rel:
				x += cx
				y += cy
			cx, cy = x, y
			ops.append(("M", x, y))
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x, y = read_num(), read_num()
				if rel:
					x += cx
					y += cy
				cx, cy = x, y
				ops.append(("L", x, y))
			continue
		if c == "L":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x, y = read_num(), read_num()
				if rel:
					x += cx
					y += cy
				cx, cy = x, y
				ops.append(("L", x, y))
			continue
		if c == "C":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x1, y1, x2, y2, x3, y3 = (
					read_num(),
					read_num(),
					read_num(),
					read_num(),
					read_num(),
					read_num(),
				)
				if rel:
					x1 += cx
					y1 += cy
					x2 += cx
					y2 += cy
					x3 += cx
					y3 += cy
				ops.append(("C", x1, y1, x2, y2, x3, y3))
				cx, cy = x3, y3
			continue
		while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
			i += 1

	return ops


def svg_file_to_paths(svg_path: Path) -> list[dict]:
	paths: list[dict] = []
	root = ET.parse(svg_path).getroot()
	ns = {"svg": "http://www.w3.org/2000/svg"}
	elements = list(root.findall(".//svg:path", ns)) + list(root.findall(".//path"))
	for el in elements:
		d = el.get("d") or ""
		if not d.strip():
			continue
		ops = parse_path_d(d)
		if len(ops) < 2:
			continue
		stroked = (el.get("fill") in (None, "none", "transparent")) and el.get("stroke") not in (
			None,
			"none",
		)
		paths.append({"ops": ops, "stroked": stroked, "closed": "Z" in [o[0] for o in ops]})
	return paths


def is_canonical_svg(content: str) -> bool:
	return bool(re.search(r'viewBox\s*=\s*["\']0\s+0\s+950\s+35["\']', content))


def inkscape_export(src: Path, dest: Path) -> None:
	inkscape = shutil.which("inkscape") or shutil.which("inkscape.exe")
	if not inkscape:
		raise RuntimeError(
			"Inkscape is required for "
			+ src.suffix.lower()
			+ " import. Install Inkscape or export SVG/LBRN2 from LightBurn."
		)
	cmd = [
		inkscape,
		str(src),
		"--export-type=svg",
		f"--export-filename={dest}",
	]
	result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
	if result.returncode != 0:
		raise RuntimeError(
			f"Inkscape failed ({result.returncode}): {result.stderr or result.stdout}"
		)
	if not dest.is_file():
		raise RuntimeError("Inkscape did not produce an SVG file.")


def convert_source(
	src: Path,
	dest: Path,
	*,
	fill_color: str = "white",
	strip_numbers: bool = True,
) -> None:
	ext = src.suffix.lower()
	conv = load_converter()

	if ext == ".lbrn2":
		lbrn = load_lbrn2_helpers()
		paths = lbrn.extract_path_shapes(src)
		if not paths:
			raise ValueError(f"No path shapes in {src.name}")
		if strip_numbers:
			paths = conv.strip_cluster_label_paths(conv.strip_model_number_paths(paths))
		conv.convert_paths(paths, dest, src.name, strip_numbers=False, fill_color=fill_color)
		return

	if ext in (".ai", ".eps"):
		conv.convert_file(src, dest, strip_numbers=strip_numbers, fill_color=fill_color)
		return

	if ext == ".svg":
		content = src.read_text(encoding="utf-8", errors="replace")
		if is_canonical_svg(content):
			dest.write_text(content, encoding="utf-8")
			return
		paths = svg_file_to_paths(src)
		if paths:
			if strip_numbers:
				paths = conv.strip_cluster_label_paths(conv.strip_model_number_paths(paths))
			conv.convert_paths(paths, dest, src.name, strip_numbers=False, fill_color=fill_color)
			return
		# Fall through: Inkscape may normalize complex SVG.
		tmp = dest.parent / (dest.stem + ".inkscape.svg")
		inkscape_export(src, tmp)
		try:
			convert_source(tmp, dest, fill_color=fill_color, strip_numbers=strip_numbers)
		finally:
			tmp.unlink(missing_ok=True)
		return

	if ext in (".dxf", ".pdf"):
		tmp = dest.parent / (dest.stem + ".via-inkscape.svg")
		inkscape_export(src, tmp)
		try:
			convert_source(tmp, dest, fill_color=fill_color, strip_numbers=strip_numbers)
		finally:
			tmp.unlink(missing_ok=True)
		return

	raise ValueError(f"Unsupported extension: {ext}")


def main() -> int:
	parser = argparse.ArgumentParser(description="Convert one line model to 950×35 SVG")
	parser.add_argument("source", type=Path, help="Source vector file")
	parser.add_argument("output", type=Path, help="Output .svg path")
	parser.add_argument("--fill-color", default="white", help="Fill/stroke color for converted paths")
	parser.add_argument(
		"--no-strip-numbers",
		action="store_true",
		help="Keep digit label paths (default: strip)",
	)
	args = parser.parse_args()

	if not args.source.is_file():
		print(f"Missing source: {args.source}", file=sys.stderr)
		return 1

	try:
		convert_source(
			args.source,
			args.output,
			fill_color=args.fill_color.strip() or "white",
			strip_numbers=not args.no_strip_numbers,
		)
	except Exception as exc:
		print(f"FAIL: {exc}", file=sys.stderr)
		return 1

	out = args.output.read_text(encoding="utf-8", errors="replace")
	if not is_canonical_svg(out):
		print("WARN: output missing canonical 950×35 viewBox", file=sys.stderr)
	print(f"OK {args.source.name} -> {args.output}")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
