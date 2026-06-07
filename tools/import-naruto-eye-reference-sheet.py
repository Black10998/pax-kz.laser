#!/usr/bin/env python3
"""
Import Naruto anime eye line models type_102–type_111 from the customer reference sheet PNG.

Requires the exact artwork (not generated geometry):
  import/naruto-eye-models/reference-sheet.png

The sheet is a 2×5 grid (10 models). Each cell is color-traced with vtracer, cropped to
the eye artwork (labels/numbers stripped), and normalized to 950×35 with native colors.
"""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path

import vtracer
from PIL import Image

CANVAS_W = 950.0
CANVAS_H = 35.0
START_TYPE = 102
MODEL_COUNT = 10
GRID_COLS = 2
GRID_ROWS = 5

SVG_NS = "http://www.w3.org/2000/svg"
ET.register_namespace("", SVG_NS)


def find_reference_image(src_dir: Path) -> Path:
	for name in (
		"reference-sheet.png",
		"reference-sheet.jpg",
		"reference-sheet.jpeg",
		"naruto-eye-reference-sheet.png",
	):
		path = src_dir / name
		if path.is_file():
			return path
	raise FileNotFoundError(
		"Missing reference sheet image. Commit your artwork at "
		f"{src_dir}/reference-sheet.png (PNG export of the numbered 2×5 sheet)."
	)


def split_grid(img: Image.Image, cols: int, rows: int) -> list[Image.Image]:
	w, h = img.size
	cell_w = w / cols
	cell_h = h / rows
	cells: list[Image.Image] = []
	for row in range(rows):
		for col in range(cols):
			x0 = int(col * cell_w)
			y0 = int(row * cell_h)
			x1 = int((col + 1) * cell_w)
			y1 = int((row + 1) * cell_h)
			cells.append(img.crop((x0, y0, x1, y1)))
	return cells


def crop_eye_artwork(cell: Image.Image) -> Image.Image:
	"""Keep eye pair region; drop bottom filename labels and left index digits."""
	w, h = cell.size
	# Bottom ~32% is typically the colored filename label strip.
	art_bottom = int(h * 0.68)
	# Left ~8% may contain row numbers like "01."
	art_left = max(0, int(w * 0.08))
	out = cell.crop((art_left, 0, w, art_bottom)).convert("RGBA")
	# Flatten onto white for stable color tracing (black sheet -> white bg).
	flat = Image.new("RGBA", out.size, (255, 255, 255, 255))
	flat.paste(out, (0, 0), out)
	return flat.convert("RGB")


def trace_color(cell_img: Image.Image, tmp_dir: Path, idx: int) -> Path:
	png = tmp_dir / f"cell_{idx:02d}.png"
	svg = tmp_dir / f"cell_{idx:02d}.svg"
	cell_img.save(png)
	vtracer.convert_image_to_svg_py(
		str(png),
		str(svg),
		colormode="color",
		hierarchical="stacked",
		mode="spline",
		filter_speckle=2,
		color_precision=8,
		layer_difference=12,
		corner_threshold=60,
		length_threshold=3.0,
		max_iterations=10,
		splice_threshold=45,
		path_precision=8,
	)
	return svg


def parse_floats(text: str) -> list[float]:
	return [float(x) for x in re.findall(r"[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?", text)]


def path_bbox(d: str) -> tuple[float, float, float, float] | None:
	nums = parse_floats(d)
	if len(nums) < 2:
		return None
	xs = nums[0::2]
	ys = nums[1::2]
	if not xs or not ys:
		return None
	return min(xs), min(ys), max(xs), max(ys)


def element_paint(el: ET.Element) -> tuple[str, str, str]:
	fill = (el.get("fill") or "none").strip()
	stroke = (el.get("stroke") or "none").strip()
	style = el.get("style") or ""
	if "fill:" in style and fill in ("", "none"):
		m = re.search(r"fill:\s*([^;]+)", style)
		if m:
			fill = m.group(1).strip()
	if "stroke:" in style and stroke in ("", "none"):
		m = re.search(r"stroke:\s*([^;]+)", style)
		if m:
			stroke = m.group(1).strip()
	return fill, stroke, style


def collect_drawables(root: ET.Element) -> list[tuple[str, str, str, str]]:
	items: list[tuple[str, str, str, str]] = []
	for el in root.iter():
		tag = el.tag.split("}")[-1] if "}" in el.tag else el.tag
		if tag == "path":
			d = el.get("d") or ""
			if not d.strip():
				continue
			fill, stroke, style = element_paint(el)
			sw = el.get("stroke-width") or ""
			if not sw and "stroke-width:" in style:
				m = re.search(r"stroke-width:\s*([^;]+)", style)
				if m:
					sw = m.group(1).strip()
			items.append((d, fill, stroke, sw))
		elif tag in ("rect", "circle", "ellipse", "polygon", "polyline"):
			# Rare in vtracer output; convert simple shapes to path-like bbox entries.
			pass
	return items


def transform_path_d(d: str, x0: float, y0: float, scale: float, ox: float, oy: float) -> str:
	"""Scale/translate path coordinates to 950×35 while preserving command structure."""
	tokens = re.findall(
		r"[MmLlHhVvCcSsQqTtAaZz]|"
		r"[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?",
		d,
	)
	out: list[str] = []
	i = 0
	cx = cy = 0.0

	def fmt(n: float) -> str:
		s = f"{n:.4f}".rstrip("0").rstrip(".")
		return s if s else "0"

	def map_x(x: float) -> float:
		return (x - x0) * scale + ox

	def map_y(y: float) -> float:
		return (y - y0) * scale + oy

	def read_num() -> float:
		nonlocal i
		v = float(tokens[i])
		i += 1
		return v

	while i < len(tokens):
		cmd = tokens[i]
		if cmd in "MmLlHhVvCcSsQqTtAaZz":
			i += 1
		else:
			cmd = "L"
		rel = cmd.islower()
		c = cmd.upper()
		if c == "Z":
			out.append("Z")
			continue
		if c == "M":
			x, y = read_num(), read_num()
			if rel:
				x += cx
				y += cy
			cx, cy = map_x(x), map_y(y)
			out.extend(["M", fmt(cx), fmt(cy)])
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x, y = read_num(), read_num()
				if rel:
					x += cx
					y += cy
				cx, cy = map_x(x), map_y(y)
				out.extend(["L", fmt(cx), fmt(cy)])
			continue
		if c == "L":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x, y = read_num(), read_num()
				if rel:
					x += cx
					y += cy
				cx, cy = map_x(x), map_y(y)
				out.extend(["L", fmt(cx), fmt(cy)])
			continue
		if c == "H":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				x = read_num()
				if rel:
					x += cx
				cx = map_x(x)
				out.extend(["L", fmt(cx), fmt(cy)])
			continue
		if c == "V":
			while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
				y = read_num()
				if rel:
					y += cy
				cy = map_y(y)
				out.extend(["L", fmt(cx), fmt(cy)])
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
				x1, y1 = map_x(x1), map_y(y1)
				x2, y2 = map_x(x2), map_y(y2)
				cx, cy = map_x(x3), map_y(y3)
				out.extend(["C", fmt(x1), fmt(y1), fmt(x2), fmt(y2), fmt(cx), fmt(cy)])
			continue
		while i < len(tokens) and tokens[i] not in "MmLlHhVvCcSsQqTtAaZz":
			i += 1

	return " ".join(out)


def normalize_traced_svg(src_svg: Path, dest: Path) -> None:
	root = ET.parse(src_svg).getroot()
	drawables = collect_drawables(root)
	if not drawables:
		raise ValueError(f"No drawable paths in {src_svg.name}")

	bboxes = [path_bbox(d) for d, _, _, _ in drawables]
	bboxes = [b for b in bboxes if b]
	if not bboxes:
		raise ValueError(f"No path bounds in {src_svg.name}")

	x0 = min(b[0] for b in bboxes)
	y0 = min(b[1] for b in bboxes)
	x1 = max(b[2] for b in bboxes)
	y1 = max(b[3] for b in bboxes)
	w = x1 - x0
	h = y1 - y0
	if w <= 0 or h <= 0:
		raise ValueError(f"Invalid bounds in {src_svg.name}")

	pad_x = max(w * 0.03, 1.0)
	pad_y = max(h * 0.06, 0.5)
	x0 -= pad_x
	y0 -= pad_y
	x1 += pad_x
	y1 += pad_y
	w = x1 - x0
	h = y1 - y0

	scale = min(CANVAS_W / w, CANVAS_H / h)
	fit_w = w * scale
	fit_h = h * scale
	ox = (CANVAS_W - fit_w) / 2.0
	oy = (CANVAS_H - fit_h) / 2.0

	lines: list[str] = []
	for d, fill, stroke, sw in drawables:
		nd = transform_path_d(d, x0, y0, scale, ox, oy)
		attrs = ['fill-rule="evenodd"', 'clip-rule="evenodd"']
		if fill and fill.lower() not in ("none", "transparent"):
			attrs.append(f'fill="{fill}"')
		else:
			attrs.append('fill="none"')
		if stroke and stroke.lower() not in ("none", "transparent"):
			attrs.append(f'stroke="{stroke}"')
			if sw:
				try:
					attrs.append(f'stroke-width="{max(float(sw) * scale, 0.35):.3f}"')
				except ValueError:
					attrs.append(f'stroke-width="{sw}"')
			attrs.append('stroke-linejoin="round"')
			attrs.append('stroke-linecap="round"')
		else:
			attrs.append('stroke="none"')
		lines.append(f'<path d="{nd}" {" ".join(attrs)}/>')

	body = "\n  ".join(lines)
	svg = (
		'<?xml version="1.0" encoding="UTF-8"?>\n'
		f'<svg width="950" height="35" viewBox="0 0 950 35" fill="none" '
		f'xmlns="http://www.w3.org/2000/svg">\n'
		f"  {body}\n"
		f"</svg>\n"
	)
	dest.parent.mkdir(parents=True, exist_ok=True)
	dest.write_text(svg, encoding="utf-8")


def main() -> int:
	parser = argparse.ArgumentParser(description="Import Naruto eye reference sheet → type_102–111")
	parser.add_argument(
		"src_dir",
		type=Path,
		nargs="?",
		default=Path(__file__).resolve().parent.parent / "import/naruto-eye-models",
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
		src = find_reference_image(args.src_dir)
	except FileNotFoundError as exc:
		print(exc, file=sys.stderr)
		return 1

	img = Image.open(src)
	if img.mode not in ("RGB", "RGBA"):
		img = img.convert("RGB")
	elif img.mode == "RGBA":
		bg = Image.new("RGB", img.size, (255, 255, 255))
		bg.paste(img, mask=img.split()[3])
		img = bg

	cells = split_grid(img, GRID_COLS, GRID_ROWS)
	if len(cells) < args.count:
		print(f"FAIL expected at least {args.count} grid cells", file=sys.stderr)
		return 1

	args.output.mkdir(parents=True, exist_ok=True)

	with tempfile.TemporaryDirectory(prefix="pckz-naruto-eye-") as tmp:
		tmp_dir = Path(tmp)
		for i in range(args.count):
			num = args.start_type + i
			eye_crop = crop_eye_artwork(cells[i])
			traced = trace_color(eye_crop, tmp_dir, i)
			dest = args.output / f"type_{num}.svg"
			normalize_traced_svg(traced, dest)
			print(f"OK type_{num}.svg from {src.name} cell {i + 1}")

	print(f"Imported {args.count} Naruto eye model(s) from {src.name}")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
