#!/usr/bin/env python3
"""
Convert LightBurn / Illustrator AI5 PostScript (.ai) line ornaments to SVG.
Preserves path geometry from the supplied artwork (no redesign).
"""

from __future__ import annotations

import argparse
import math
import re
import sys
from pathlib import Path

DRAWING_START = "%%EndSetup"
DRAWING_END = "%%PageTrailer"

# Cloudlift / Shopify line ornaments use a fixed artboard (types 1–20).
CANVAS_W = 950.0
CANVAS_H = 35.0
FILL_COLOR = "white"

# Ledos layer refs — text clearance band on the 950×35 line artboard (types 1–20 use side geometry + open center).
DESIGN_W = 3651.0
TEXT_REF_X = 1136.0
TEXT_REF_W = 1392.0
LINES_REF_X = 609.0
LINES_REF_W = 2424.0
PLATE_MARGIN_X = 9.5
TEXT_GAP_PAD_X = 6.0
SKIP_TOKENS = {
	"Lb",
	"Ln",
	"XR",
	"A",
	"O",
	"J",
	"j",
	"w",
	"M",
	"d",
	"[]0",
	"K",
	"(Layer",
	"1)",
	"1",
	"0",
}


def parse_bbox(content: str) -> tuple[float, float, float, float]:
	m = re.search(
		r"%%HiResBoundingBox:\s*([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)",
		content,
	)
	if m:
		return tuple(float(g) for g in m.groups())
	m = re.search(
		r"%%BoundingBox:\s*([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)",
		content,
	)
	if m:
		return tuple(float(g) for g in m.groups())
	return 0.0, 0.0, 100.0, 100.0


def extract_drawing_tokens(content: str) -> list[str]:
	start = content.find(DRAWING_START)
	end = content.find(DRAWING_END)
	if start < 0 or end < 0:
		return []
	section = content[start:end]
	# Drop parenthetical labels like (Layer 1)
	section = re.sub(r"\([^)]*\)", " ", section)
	return section.split()


def is_number(token: str) -> bool:
	try:
		float(token)
		return True
	except ValueError:
		return False


def parse_paths(tokens: list[str]) -> list[dict]:
	paths: list[dict] = []
	current: dict | None = None
	i = 0
	nums: list[float] = []

	def flush_stroke() -> None:
		nonlocal current
		if current and current.get("ops"):
			paths.append(current)
		current = None

	def ensure_path(stroked: bool) -> None:
		nonlocal current
		if current is None:
			current = {"ops": [], "stroked": stroked, "closed": False}

	while i < len(tokens):
		t = tokens[i]
		if t in SKIP_TOKENS or (t.startswith("(") and t.endswith(")")):
			i += 1
			continue
		if is_number(t):
			nums.append(float(t))
			i += 1
			continue
		if t == "m" and len(nums) >= 2:
			flush_stroke()
			current = {"ops": [("M", nums[-2], nums[-1])], "stroked": False, "closed": False}
			nums = []
			i += 1
			continue
		if t in ("L", "l") and len(nums) >= 2 and current:
			current["ops"].append(("L", nums[-2], nums[-1]))
			nums = []
			i += 1
			continue
		if t == "C" and len(nums) >= 6 and current:
			x1, y1, x2, y2, x3, y3 = nums[-6:]
			current["ops"].append(("C", x1, y1, x2, y2, x3, y3))
			nums = []
			i += 1
			continue
		if t == "S" and current:
			current["stroked"] = True
			flush_stroke()
			nums = []
			i += 1
			continue
		if t in ("s", "f", "F") and current:
			current["closed"] = True
			if t == "s":
				current["ops"].append(("Z",))
			flush_stroke()
			nums = []
			i += 1
			continue
		nums = []
		i += 1

	flush_stroke()
	return paths


def collect_points(paths: list[dict]) -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = []
	for path in paths:
		for op in path["ops"]:
			if op[0] in ("M", "L"):
				pts.append((op[1], op[2]))
			elif op[0] == "C":
				pts.extend([(op[1], op[2]), (op[3], op[4]), (op[5], op[6])])
	return pts


def fmt(n: float) -> str:
	return format(n, ".4f").rstrip("0").rstrip(".") or "0"


def map_point(x: float, y: float, x0: float, ymax: float, scale: float, ox: float, oy: float) -> tuple[float, float]:
	lx = x - x0
	ly = ymax - y
	return ox + lx * scale, oy + ly * scale


def ops_to_d(
	ops: list,
	x0: float,
	ymax: float,
	scale: float,
	ox: float,
	oy: float,
) -> str:
	parts: list[str] = []
	for op in ops:
		if op[0] == "M":
			x, y = map_point(op[1], op[2], x0, ymax, scale, ox, oy)
			parts.append(f"M{fmt(x)} {fmt(y)}")
		elif op[0] == "L":
			x, y = map_point(op[1], op[2], x0, ymax, scale, ox, oy)
			parts.append(f"L{fmt(x)} {fmt(y)}")
		elif op[0] == "C":
			cx1, cy1 = map_point(op[1], op[2], x0, ymax, scale, ox, oy)
			cx2, cy2 = map_point(op[3], op[4], x0, ymax, scale, ox, oy)
			cx3, cy3 = map_point(op[5], op[6], x0, ymax, scale, ox, oy)
			parts.append(
				f"C{fmt(cx1)} {fmt(cy1)} {fmt(cx2)} {fmt(cy2)} {fmt(cx3)} {fmt(cy3)}"
			)
		elif op[0] == "Z":
			parts.append("Z")
	return " ".join(parts)


def cloudlift_text_band_x() -> tuple[float, float]:
	"""Open center band for customer text (same proportions as Cloudlift text layer ref)."""
	text_center_rel = (TEXT_REF_X + TEXT_REF_W * 0.5 - LINES_REF_X) / LINES_REF_W
	text_half_rel = (TEXT_REF_W * 0.5) / LINES_REF_W
	cx = text_center_rel * CANVAS_W
	hw = text_half_rel * CANVAS_W + TEXT_GAP_PAD_X
	return cx - hw, cx + hw


def path_endpoints_canvas(
	ops: list,
	x0: float,
	ymax: float,
	scale: float,
	ox: float,
	oy: float,
) -> tuple[tuple[float, float], tuple[float, float]] | None:
	pts: list[tuple[float, float]] = []
	for op in ops:
		if op[0] in ("M", "L"):
			pts.append(map_point(op[1], op[2], x0, ymax, scale, ox, oy))
		elif op[0] == "C":
			pts.append(map_point(op[5], op[6], x0, ymax, scale, ox, oy))
	if len(pts) < 2:
		return None
	return pts[0], pts[-1]


def is_horizontal_runner(p0: tuple[float, float], p1: tuple[float, float]) -> bool:
	dx = abs(p1[0] - p0[0])
	dy = abs(p1[1] - p0[1])
	return dx >= 30.0 and dy <= 4.0 and dx > dy * 4.0


def stroke_line_path(x1: float, x2: float, y: float, stroke_w: float) -> str:
	if x2 < x1:
		x1, x2 = x2, x1
	if x2 - x1 < 0.5:
		return ""
	return (
		f'<path d="M{fmt(x1)} {fmt(y)} L{fmt(x2)} {fmt(y)}" fill="none" '
		f'stroke="{FILL_COLOR}" stroke-width="{fmt(stroke_w)}" '
		f'stroke-linecap="round" stroke-linejoin="round"/>'
	)


def clip_runner_outside_text_band(
	x1: float,
	x2: float,
	y: float,
	gap_x0: float,
	gap_x1: float,
	stroke_w: float,
) -> list[str]:
	"""Keep only runner segments outside the text band (no geometry under text)."""
	if x2 < x1:
		x1, x2 = x2, x1
	right_edge = CANVAS_W - PLATE_MARGIN_X
	out: list[str] = []
	if x1 < gap_x0 - 0.25:
		seg = stroke_line_path(x1, min(x2, gap_x0), y, stroke_w)
		if seg:
			out.append(seg)
	if x2 > gap_x1 + 0.25:
		seg = stroke_line_path(max(x1, gap_x1), x2, y, stroke_w)
		if seg:
			out.append(seg)
	return out


def side_runners_for_text_band(y: float, gap_x0: float, gap_x1: float, stroke_w: float) -> list[str]:
	"""Like CDN type 5: runners on plate sides, open center for text (ornaments unchanged)."""
	out: list[str] = []
	if gap_x0 > PLATE_MARGIN_X + 1.0:
		seg = stroke_line_path(PLATE_MARGIN_X, gap_x0, y, stroke_w)
		if seg:
			out.append(seg)
	right_edge = CANVAS_W - PLATE_MARGIN_X
	if right_edge > gap_x1 + 1.0:
		seg = stroke_line_path(gap_x1, right_edge, y, stroke_w)
		if seg:
			out.append(seg)
	return out


def runner_intersects_text_band(x1: float, x2: float, gap_x0: float, gap_x1: float) -> bool:
	if x2 < x1:
		x1, x2 = x2, x1
	return x1 < gap_x1 - 0.25 and x2 > gap_x0 + 0.25


def is_plate_side_runner(x1: float, x2: float, gap_x0: float, gap_x1: float) -> bool:
	"""Already a left or right plate runner (like CDN type 5)."""
	if x2 < x1:
		x1, x2 = x2, x1
	left = x1 <= PLATE_MARGIN_X + 2.0 and x2 <= gap_x0 + 2.0
	right = x1 >= gap_x1 - 2.0 and x2 >= CANVAS_W - PLATE_MARGIN_X - 2.0
	return left or right


def path_to_svg_entries(
	path: dict,
	x0: float,
	ymax: float,
	scale: float,
	ox: float,
	oy: float,
	gap_x0: float,
	gap_x1: float,
	stroke_w: float,
) -> list[str]:
	if path.get("stroked"):
		endpoints = path_endpoints_canvas(path["ops"], x0, ymax, scale, ox, oy)
		if endpoints and is_horizontal_runner(endpoints[0], endpoints[1]):
			x1, x2 = endpoints[0][0], endpoints[1][0]
			y = (endpoints[0][1] + endpoints[1][1]) / 2.0
			if runner_intersects_text_band(x1, x2, gap_x0, gap_x1):
				if is_plate_side_runner(x1, x2, gap_x0, gap_x1):
					return clip_runner_outside_text_band(x1, x2, y, gap_x0, gap_x1, stroke_w)
				return side_runners_for_text_band(y, gap_x0, gap_x1, stroke_w)
			return clip_runner_outside_text_band(x1, x2, y, gap_x0, gap_x1, stroke_w)
		d = ops_to_d(path["ops"], x0, ymax, scale, ox, oy)
		if not d:
			return []
		return [
			f'<path d="{d}" fill="none" stroke="{FILL_COLOR}" stroke-width="{fmt(stroke_w)}" '
			f'stroke-linecap="round" stroke-linejoin="round"/>'
		]

	d = ops_to_d(path["ops"], x0, ymax, scale, ox, oy)
	if not d:
		return []
	return [
		f'<path d="{d}" fill-rule="evenodd" clip-rule="evenodd" fill="{FILL_COLOR}" stroke="none"/>'
	]


def convert_file(src: Path, dest: Path) -> None:
	content = src.read_text(encoding="utf-8", errors="replace")
	paths = parse_paths(extract_drawing_tokens(content))
	if not paths:
		raise ValueError(f"No paths found in {src}")

	points = collect_points(paths)
	if not points:
		raise ValueError(f"No coordinates in {src}")

	xs = [p[0] for p in points]
	ys = [p[1] for p in points]
	x0, x1 = min(xs), max(xs)
	y0, y1 = min(ys), max(ys)
	# Small padding (fraction of span) for clean viewBox.
	pad_x = max((x1 - x0) * 0.02, 0.5)
	pad_y = max((y1 - y0) * 0.05, 0.5)
	x0 -= pad_x
	x1 += pad_x
	y0 -= pad_y
	y1 += pad_y
	w = x1 - x0
	h = y1 - y0
	if w <= 0 or h <= 0:
		raise ValueError(f"Invalid bounds in {src}")

	scale = min(CANVAS_W / w, CANVAS_H / h)
	fit_w = w * scale
	fit_h = h * scale
	ox = (CANVAS_W - fit_w) / 2.0
	oy = (CANVAS_H - fit_h) / 2.0
	stroke_w = max(0.35 * scale, 0.5)
	gap_x0, gap_x1 = cloudlift_text_band_x()

	svg_paths: list[str] = []
	seen_side_runner_y: set[float] = set()
	for path in paths:
		if path.get("stroked"):
			endpoints = path_endpoints_canvas(path["ops"], x0, y1, scale, ox, oy)
			if endpoints and is_horizontal_runner(endpoints[0], endpoints[1]):
				x1, x2 = endpoints[0][0], endpoints[1][0]
				y = round((endpoints[0][1] + endpoints[1][1]) / 2.0, 1)
				if runner_intersects_text_band(x1, x2, gap_x0, gap_x1) and not is_plate_side_runner(
					x1, x2, gap_x0, gap_x1
				):
					if y in seen_side_runner_y:
						continue
					seen_side_runner_y.add(y)
		svg_paths.extend(
			path_to_svg_entries(path, x0, y1, scale, ox, oy, gap_x0, gap_x1, stroke_w)
		)

	inner = "\n  ".join(svg_paths)
	svg = (
		f'<?xml version="1.0" encoding="UTF-8"?>\n'
		f'<svg width="{int(CANVAS_W)}" height="{int(CANVAS_H)}" '
		f'viewBox="0 0 {int(CANVAS_W)} {int(CANVAS_H)}" fill="none" '
		f'xmlns="http://www.w3.org/2000/svg">\n'
		f"  {inner}\n"
		f"</svg>\n"
	)
	dest.parent.mkdir(parents=True, exist_ok=True)
	dest.write_text(svg, encoding="utf-8")


def main() -> int:
	parser = argparse.ArgumentParser(description="Convert LightBurn .ai to SVG")
	parser.add_argument("source", type=Path, help="Source .ai file or directory")
	parser.add_argument(
		"-o",
		"--output",
		type=Path,
		default=Path("public/assets/lines"),
		help="Output directory for type_NN.svg",
	)
	args = parser.parse_args()

	mapping = {
		"model 21": 21,
		"model 22": 22,
		"model 23": 23,
		"model 24": 24,
		"model 25": 25,
		"model 26": 26,
		"model27": 27,
		"model 28": 28,
		"model 29": 29,
		"model 30": 30,
		"model31": 31,
		"model 32": 32,
		"model 33": 33,
		"model 34": 34,
		"model 35": 35,
		"model 36": 36,
		"model 37": 37,
		"model 38": 38,
	}

	sources: list[Path] = []
	if args.source.is_dir():
		sources = sorted(args.source.glob("*.ai"))
	else:
		sources = [args.source]

	ok = 0
	for src in sources:
		stem = src.stem
		num = mapping.get(stem)
		if num is None:
			m = re.match(r"model\s*(\d+)", stem, re.I)
			if m:
				num = int(m.group(1))
		if num is None:
			print(f"SKIP unknown name: {src.name}", file=sys.stderr)
			continue
		dest = args.output / f"type_{num}.svg"
		try:
			convert_file(src, dest)
			print(f"OK {src.name} -> {dest}")
			ok += 1
		except Exception as exc:
			print(f"FAIL {src.name}: {exc}", file=sys.stderr)
			return 1

	if ok != 18:
		print(f"Expected 18 files, converted {ok}", file=sys.stderr)
		return 1
	return 0


if __name__ == "__main__":
	sys.exit(main())
