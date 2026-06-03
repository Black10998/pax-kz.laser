#!/usr/bin/env python3
"""
Import customer red line models type_102–type_111 from a reference sheet image.

Requires the exact artwork file (not generated geometry):
  import/vector-line-customer-red/reference-sheet.png
  import/vector-line-customer-red/reference-sheet.jpg

Splits the sheet into horizontal rows, strips left-side digit labels, vector-traces
each row with vtracer, and writes 950×35 SVGs via convert-lightburn-ai-to-svg.py.
"""

from __future__ import annotations

import argparse
import importlib.util
import re
import sys
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path

import vtracer
from PIL import Image

MATTE_RED = "#B22222"
START_TYPE = 102
MODEL_COUNT = 10
CANVAS_W = 950.0
CANVAS_H = 35.0


def load_converter():
	root = Path(__file__).resolve().parent
	spec = importlib.util.spec_from_file_location(
		"pckz_convert_ai", root / "convert-lightburn-ai-to-svg.py"
	)
	mod = importlib.util.module_from_spec(spec)
	assert spec.loader
	spec.loader.exec_module(mod)
	return mod


def find_reference_image(src_dir: Path) -> Path:
	candidates = (
		"reference-sheet.png",
		"reference-sheet.jpg",
		"reference-sheet.jpeg",
		"reference sheet.png",
		"line-reference-sheet.png",
	)
	for name in candidates:
		path = src_dir / name
		if path.is_file():
			return path
	raise FileNotFoundError(
		"Missing reference sheet image. Place your artwork at "
		f"{src_dir}/reference-sheet.png (PNG export of the numbered sheet)."
	)


def parse_path_d(d: str) -> list[tuple[str, float, float]]:
	"""Parse SVG path d into M/L/C/Z ops (absolute coordinates only)."""
	tokens = re.findall(
		r"[MmLlHhVvCcSsQqTtAaZz]|"
		r"[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?",
		d,
	)
	ops: list[tuple[str, float, float]] = []
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
		if c == "H":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x = read_num()
				if rel:
					x += cx
				cx = x
				ops.append(("L", cx, cy))
			continue
		if c == "V":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				y = read_num()
				if rel:
					y += cy
				cy = y
				ops.append(("L", cx, cy))
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
		# Skip unsupported commands by consuming number runs.
		while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
			i += 1

	return ops


def svg_file_to_paths(svg_path: Path) -> list[dict]:
	paths: list[dict] = []
	root = ET.parse(svg_path).getroot()
	ns = {"svg": "http://www.w3.org/2000/svg"}
	for el in root.findall(".//svg:path", ns) + root.findall(".//path"):
		d = el.get("d") or ""
		if not d.strip():
			continue
		ops = parse_path_d(d)
		if len(ops) < 2:
			continue
		closed = "Z" in [o[0] for o in ops]
		stroked = (el.get("fill") in (None, "none", "transparent")) and el.get("stroke") not in (
			None,
			"none",
		)
		paths.append({"ops": ops, "stroked": stroked, "closed": closed})
	return paths


def strip_number_column(img: Image.Image, label_frac: float = 0.07) -> Image.Image:
	"""Mask left strip where row digits usually sit."""
	w, h = img.size
	cut = max(8, int(w * label_frac))
	out = img.convert("RGBA")
	pixels = out.load()
	for y in range(h):
		for x in range(cut):
			pixels[x, y] = (255, 255, 255, 0)
	return out


def split_rows(img: Image.Image, take_count: int, total_rows: int) -> list[Image.Image]:
	"""Split sheet into total_rows bands; return the first take_count (designs 1–10)."""
	w, h = img.size
	row_h = h / max(total_rows, 1)
	rows: list[Image.Image] = []
	for i in range(take_count):
		y0 = int(i * row_h)
		y1 = int((i + 1) * row_h)
		rows.append(img.crop((0, y0, w, y1)))
	return rows


def trace_row(row_img: Image.Image, tmp_dir: Path, idx: int) -> Path:
	png = tmp_dir / f"row_{idx:02d}.png"
	svg = tmp_dir / f"row_{idx:02d}.svg"
	# vtracer expects dark shapes on light background.
	bw = row_img.convert("RGB")
	bw.save(png)
	vtracer.convert_image_to_svg_py(
		str(png),
		str(svg),
		colormode="binary",
		hierarchical="stacked",
		mode="spline",
		filter_speckle=2,
		color_precision=6,
		layer_difference=16,
		corner_threshold=60,
		length_threshold=4.0,
		max_iterations=10,
		splice_threshold=45,
		path_precision=8,
	)
	return svg


def main() -> int:
	parser = argparse.ArgumentParser(description="Import reference sheet PNG → type_102–111")
	parser.add_argument(
		"src_dir",
		type=Path,
		nargs="?",
		default=Path(__file__).resolve().parent.parent / "import/vector-line-customer-red",
	)
	parser.add_argument(
		"-o",
		"--output",
		type=Path,
		default=Path(__file__).resolve().parent.parent / "public/assets/lines",
	)
	parser.add_argument("--start-type", type=int, default=START_TYPE)
	parser.add_argument("--count", type=int, default=MODEL_COUNT)
	parser.add_argument(
		"--sheet-rows",
		type=int,
		default=15,
		help="Total numbered rows on the sheet (default 15); imports first --count rows.",
	)
	parser.add_argument("--fill-color", default=MATTE_RED)
	args = parser.parse_args()

	try:
		src = find_reference_image(args.src_dir)
	except FileNotFoundError as exc:
		print(exc, file=sys.stderr)
		return 1

	conv = load_converter()
	img = Image.open(src)
	if img.mode not in ("RGB", "RGBA"):
		img = img.convert("RGB")

	total_rows = max(args.sheet_rows, args.count)
	rows = split_rows(img, args.count, total_rows)

	args.output.mkdir(parents=True, exist_ok=True)

	with tempfile.TemporaryDirectory(prefix="pckz-ref-sheet-") as tmp:
		tmp_dir = Path(tmp)
		for i, row in enumerate(rows):
			num = args.start_type + i
			row_clean = strip_number_column(row)
			svg_path = trace_row(row_clean, tmp_dir, i)
			paths = svg_file_to_paths(svg_path)
			if not paths:
				print(f"FAIL no paths traced for row {i + 1} -> type_{num}", file=sys.stderr)
				return 1
			dest = args.output / f"type_{num}.svg"
			conv.convert_paths(
				paths,
				dest,
				f"{src.name}#row{i + 1}",
				strip_numbers=False,
				fill_color=args.fill_color,
			)
			print(f"OK type_{num}.svg from {src.name} row {i + 1} ({len(paths)} paths)")

	print(f"Imported {args.count} model(s) from {src.name}")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
