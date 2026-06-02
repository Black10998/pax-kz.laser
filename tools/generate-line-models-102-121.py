#!/usr/bin/env python3
"""
Generate bundled matte-red line ornaments type_102–type_121 (950×35 SVG paths).
Reference sheet: 10 motif rows × 2 left/right column variants (20 slugs).
Runners match CDN type_1–20 / bundled type_92–101 (9.5→352, 598→940.5).
"""

from __future__ import annotations

import math
from pathlib import Path

CANVAS_W = 950.0
CANVAS_H = 35.0
CY = 17.5
GAP_X0 = 352.0
GAP_X1 = 598.0
MARGIN_L = 9.5
MARGIN_R = 940.5
RUNNER_Y = CY
MATTE_RED = "#B22222"
STROKE_W = 1.2


def fmt(n: float) -> str:
	return format(n, ".4f").rstrip("0").rstrip(".") or "0"


def pts_to_d(points: list[tuple[float, float]], close: bool = True) -> str:
	if len(points) < 2:
		return ""
	parts = [f"M{fmt(points[0][0])} {fmt(points[0][1])}"]
	for x, y in points[1:]:
		parts.append(f"L{fmt(x)} {fmt(y)}")
	if close:
		parts.append("Z")
	return " ".join(parts)


def circle_pts(cx: float, cy: float, r: float, steps: int = 16) -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = []
	for i in range(steps):
		a = 2 * math.pi * i / steps
		pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
	return pts


def hex_pts(cx: float, cy: float, r: float, rot: float = 0.0) -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = []
	for i in range(6):
		a = rot + math.pi / 6 + i * math.pi / 3
		pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
	return pts


def translate_pts(points: list[tuple[float, float]], ox: float, oy: float) -> list[tuple[float, float]]:
	return [(x + ox, y + oy) for x, y in points]


def flip_cap(points: list[tuple[float, float]]) -> list[tuple[float, float]]:
	w = max(p[0] for p in points)
	return [(w - x, y) for x, y in points]


def runners() -> list[str]:
	y = RUNNER_Y
	return [
		f'M{fmt(MARGIN_L)} {fmt(y)} L{fmt(GAP_X0)} {fmt(y)}',
		f'M{fmt(GAP_X1)} {fmt(y)} L{fmt(MARGIN_R)} {fmt(y)}',
	]


# --- Row 1: Jagged / shard (102 left col, 103 right col) ---


def cap_shard_a() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(8, CY - 14),
		(14, CY - 5),
		(18, CY - 13),
		(24, CY - 2),
		(30, CY - 11),
		(36, CY - 4),
		(42, CY - 12),
		(48, CY - 1),
		(52, CY),
		(48, CY + 1),
		(42, CY + 12),
		(36, CY + 4),
		(30, CY + 11),
		(24, CY + 2),
		(18, CY + 13),
		(14, CY + 5),
		(8, CY + 14),
	]


def cap_shard_b() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(6, CY - 12),
		(12, CY - 6),
		(16, CY - 11),
		(22, CY - 4),
		(28, CY - 10),
		(34, CY - 3),
		(40, CY - 9),
		(46, CY),
		(40, CY + 9),
		(34, CY + 3),
		(28, CY + 10),
		(22, CY + 4),
		(16, CY + 11),
		(12, CY + 6),
		(6, CY + 12),
	]


# --- Row 2: Tech / hex + circuit lines (104, 105) ---


def cap_tech_a_paths() -> list[list[tuple[float, float]]]:
	main = hex_pts(8, CY, 7.5)
	extras = [
		[(20, CY - 1.2), (38, CY - 1.2)],
		[(20, CY + 1.2), (34, CY + 1.2)],
		[(24, CY - 5), (32, CY - 5)],
		[(26, CY + 5), (36, CY + 5)],
	]
	nodes = [circle_pts(38, CY - 1.2, 1.4), circle_pts(34, CY + 1.2, 1.4), circle_pts(32, CY - 5, 1.2)]
	return [main] + extras + nodes


def cap_tech_b_paths() -> list[list[tuple[float, float]]]:
	main = hex_pts(10, CY, 7.0)
	extras = [
		[(22, CY), (44, CY)],
		[(22, CY - 4), (40, CY - 4)],
		[(22, CY + 4), (36, CY + 4)],
		[(28, CY - 7), (42, CY - 7)],
	]
	nodes = [circle_pts(44, CY, 1.5), circle_pts(40, CY - 4, 1.3), circle_pts(42, CY - 7, 1.2)]
	return [main] + extras + nodes


# --- Row 3: Filigree scroll (106, 107) ---


def cap_filigree_a() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(5, CY - 7),
		(10, CY - 11),
		(16, CY - 9),
		(21, CY - 13),
		(27, CY - 6),
		(33, CY - 11),
		(39, CY - 4),
		(44, CY - 8),
		(50, CY),
		(44, CY + 8),
		(39, CY + 4),
		(33, CY + 11),
		(27, CY + 6),
		(21, CY + 13),
		(16, CY + 9),
		(10, CY + 11),
		(5, CY + 7),
	]


def cap_filigree_b() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(4, CY - 6),
		(9, CY - 10),
		(15, CY - 12),
		(22, CY - 7),
		(28, CY - 12),
		(35, CY - 5),
		(41, CY - 9),
		(48, CY - 2),
		(54, CY),
		(48, CY + 2),
		(41, CY + 9),
		(35, CY + 5),
		(28, CY + 12),
		(22, CY + 7),
		(15, CY + 12),
		(9, CY + 10),
		(4, CY + 6),
	]


# --- Row 4: Modern bracket + dot (108 dot outer-left, 109 dot outer-right) ---


def cap_modern_a() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(4, CY),
		(8, CY - 6),
		(14, CY - 6),
		(14, CY - 3),
		(20, CY - 3),
		(20, CY),
		(14, CY),
		(14, CY + 3),
		(20, CY + 3),
		(20, CY + 6),
		(14, CY + 6),
		(8, CY + 6),
	]


def cap_modern_a_dot() -> list[tuple[float, float]]:
	return circle_pts(2, CY, 2.2)


def cap_modern_b() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(6, CY - 6),
		(12, CY - 6),
		(12, CY - 3),
		(18, CY - 3),
		(18, CY),
		(12, CY),
		(12, CY + 3),
		(18, CY + 3),
		(18, CY + 6),
		(12, CY + 6),
		(6, CY + 6),
	]


def cap_modern_b_dot() -> list[tuple[float, float]]:
	return circle_pts(50, CY, 2.2)


# --- Row 5: Organic flame (110, 111) ---


def cap_flame_a() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for dx, top, mid in [(6, -10, -5), (12, -13, -7), (18, -11, -4), (24, -12, -3), (30, -9, -2), (36, -7, -1)]:
		pts.extend([(dx, CY + top), (dx + 5, CY + mid), (dx + 2, CY)])
	pts.append((48, CY))
	for dx, top, mid in [(36, -7, -1), (30, -9, -2), (24, -12, -3), (18, -11, -4), (12, -13, -7), (6, -10, -5)]:
		pts.extend([(dx + 2, CY), (dx + 5, CY - mid), (dx, CY - top)])
	return pts


def cap_flame_b() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for dx, top, mid in [(5, -9, -4), (10, -12, -6), (16, -10, -3), (22, -11, -2), (28, -8, -1), (34, -6, 0), (40, -5, 1)]:
		pts.extend([(dx, CY + top), (dx + 4, CY + mid), (dx + 1, CY)])
	pts.append((46, CY))
	for dx, top, mid in [(40, -5, 1), (34, -6, 0), (28, -8, -1), (22, -11, -2), (16, -10, -3), (10, -12, -6), (5, -9, -4)]:
		pts.extend([(dx + 1, CY), (dx + 4, CY - mid), (dx, CY - top)])
	return pts


# --- Row 6: Eight-point star + diamonds (112, 113) ---


def cap_star_a() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i in range(8):
		a = math.radians(-90 + i * 45)
		r = 14 if i % 2 == 0 else 6
		pts.append((8 + r * math.cos(a), CY + r * math.sin(a)))
	pts.append((16, CY))
	return pts


def cap_star_a_diamonds() -> list[list[tuple[float, float]]]:
	return [
		[(22, CY - 3), (26, CY), (22, CY + 3)],
		[(30, CY - 3), (34, CY), (30, CY + 3)],
	]


def cap_star_b() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i in range(8):
		a = math.radians(-90 + i * 45)
		r = 15 if i % 2 == 0 else 5.5
		pts.append((6 + r * math.cos(a), CY + r * math.sin(a)))
	pts.append((14, CY))
	return pts


def cap_star_b_diamonds() -> list[list[tuple[float, float]]]:
	return [
		[(20, CY - 2.5), (23.5, CY), (20, CY + 2.5)],
		[(28, CY - 2.5), (31.5, CY), (28, CY + 2.5)],
		[(36, CY - 2.5), (39.5, CY), (36, CY + 2.5)],
	]


# --- Row 7: Tribal intertwined loops (114, 115) ---


def cap_tribal_a() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(8, CY - 10),
		(16, CY - 6),
		(22, CY - 12),
		(30, CY - 4),
		(38, CY - 10),
		(46, CY),
		(38, CY + 10),
		(30, CY + 4),
		(22, CY + 12),
		(16, CY + 6),
		(8, CY + 10),
	]


def cap_tribal_b() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(6, CY - 11),
		(14, CY - 8),
		(20, CY - 13),
		(28, CY - 5),
		(36, CY - 11),
		(44, CY - 2),
		(50, CY),
		(44, CY + 2),
		(36, CY + 11),
		(28, CY + 5),
		(20, CY + 13),
		(14, CY + 8),
		(6, CY + 11),
	]


# --- Row 8: Fletching chevrons (116, 117) ---


def cap_fletch_a() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i, (y0, y1) in enumerate([(-12, -8), (-8, -5), (-4, -2), (0, 2), (4, 5), (8, 8)]):
		x0 = 4 + i * 7
		pts.extend([(x0, CY + y0), (x0 + 10, CY + y1), (x0 + 3, CY)])
	pts.append((52, CY))
	for i, (y0, y1) in enumerate(reversed([(-12, -8), (-8, -5), (-4, -2), (0, 2), (4, 5), (8, 8)])):
		x0 = 4 + i * 7
		pts.extend([(x0 + 3, CY), (x0 + 10, CY - y1), (x0, CY - y0)])
	return pts


def cap_fletch_b() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i, (y0, y1) in enumerate([(-10, -6), (-7, -4), (-3, -1), (1, 2), (5, 5), (9, 8), (13, 10)]):
		x0 = 3 + i * 6
		pts.extend([(x0, CY + y0), (x0 + 8, CY + y1), (x0 + 2, CY)])
	pts.append((50, CY))
	for i, (y0, y1) in enumerate(reversed([(-10, -6), (-7, -4), (-3, -1), (1, 2), (5, 5), (9, 8), (13, 10)])):
		x0 = 3 + i * 6
		pts.extend([(x0 + 2, CY), (x0 + 8, CY - y1), (x0, CY - y0)])
	return pts


# --- Row 9: Hexagonal cluster (118, 119) ---


def cap_hex_cluster_a() -> list[list[tuple[float, float]]]:
	return [
		hex_pts(10, CY, 5),
		hex_pts(22, CY - 6, 3.5),
		hex_pts(22, CY + 6, 3.5),
		hex_pts(34, CY, 4),
		hex_pts(42, CY - 5, 2.8),
		hex_pts(42, CY + 5, 2.8),
	]


def cap_hex_cluster_b() -> list[list[tuple[float, float]]]:
	return [
		hex_pts(8, CY, 4.5),
		hex_pts(18, CY, 3.2),
		hex_pts(28, CY - 7, 3.8),
		hex_pts(28, CY + 7, 3.8),
		hex_pts(38, CY - 4, 3),
		hex_pts(38, CY + 4, 3),
		hex_pts(46, CY, 2.5),
	]


# --- Row 10: Crescent + four-point star (120, 121) ---


def cap_crescent_a() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = []
	for i in range(20):
		a = math.pi * 0.55 + i * (math.pi * 0.35 / 19)
		pts.append((6 + 38 * math.cos(a), CY + 11 * math.sin(a)))
	pts.append((6, CY))
	return pts


def cap_crescent_a_star() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(48, CY)]
	for i in range(4):
		a = math.radians(-90 + i * 90)
		r = 5 if i % 2 == 0 else 2
		pts.append((48 + r * math.cos(a), CY + r * math.sin(a)))
	return pts


def cap_crescent_b() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = []
	for i in range(18):
		a = math.pi * 0.5 + i * (math.pi * 0.4 / 17)
		pts.append((4 + 40 * math.cos(a), CY + 12 * math.sin(a)))
	pts.append((4, CY))
	return pts


def cap_crescent_b_star() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(50, CY)]
	for i in range(4):
		a = math.radians(-90 + i * 90)
		r = 4.5 if i % 2 == 0 else 1.8
		pts.append((50 + r * math.cos(a), CY + r * math.sin(a)))
	return pts


# --- Model registry: (filled paths, stroke segments, extra fills) ---

ModelSpec = tuple[
	list[list[tuple[float, float]]],
	list[list[tuple[float, float]]],
	list[list[tuple[float, float]]],
]


def spec_single(
	main: list[tuple[float, float]],
	extras: list[list[tuple[float, float]]] | None = None,
) -> ModelSpec:
	return ([main], [], extras or [])


def spec_multi(
	fills: list[list[tuple[float, float]]],
	strokes: list[list[tuple[float, float]]] | None = None,
	extras: list[list[tuple[float, float]]] | None = None,
) -> ModelSpec:
	return (fills, strokes or [], extras or [])


def spec_tech(paths: list[list[tuple[float, float]]]) -> ModelSpec:
	return ([paths[0]], paths[1:4], paths[4:])


CAP_MODELS: list[ModelSpec] = [
	spec_single(cap_shard_a()),
	spec_single(cap_shard_b()),
	spec_tech(cap_tech_a_paths()),
	spec_tech(cap_tech_b_paths()),
	spec_single(cap_filigree_a()),
	spec_single(cap_filigree_b()),
	spec_single(cap_modern_a(), [cap_modern_a_dot()]),
	spec_single(cap_modern_b(), [cap_modern_b_dot()]),
	spec_single(cap_flame_a()),
	spec_single(cap_flame_b()),
	spec_single(cap_star_a(), cap_star_a_diamonds()),
	spec_single(cap_star_b(), cap_star_b_diamonds()),
	spec_single(cap_tribal_a()),
	spec_single(cap_tribal_b()),
	spec_single(cap_fletch_a()),
	spec_single(cap_fletch_b()),
	spec_multi(cap_hex_cluster_a()),
	spec_multi(cap_hex_cluster_b()),
	spec_single(cap_crescent_a(), [cap_crescent_a_star()]),
	spec_single(cap_crescent_b(), [cap_crescent_b_star()]),
]


def path_width(points: list[tuple[float, float]]) -> float:
	return max(p[0] for p in points)


def stroke_path(points: list[tuple[float, float]], color: str) -> str:
	if len(points) == 2:
		return (
			f'<path d="{pts_to_d(points, close=False)}" fill="none" stroke="{color}" '
			f'stroke-width="{STROKE_W}" stroke-linecap="round" stroke-linejoin="round"/>'
		)
	return (
		f'<path d="{pts_to_d(points, close=False)}" fill="none" stroke="{color}" '
		f'stroke-width="{STROKE_W}" stroke-linecap="round"/>'
	)


def fill_path(points: list[tuple[float, float]], color: str) -> str:
	return (
		f'<path d="{pts_to_d(points)}" fill-rule="evenodd" clip-rule="evenodd" '
		f'fill="{color}" stroke="none"/>'
	)


def place_cap(
	points: list[tuple[float, float]], gap_edge: float, mirror: bool
) -> list[tuple[float, float]]:
	w = path_width(points)
	if mirror:
		pts = flip_cap(points)
		return translate_pts(pts, gap_edge, 0)
	return translate_pts(points, gap_edge - w, 0)


def build_line_svg(spec: ModelSpec) -> str:
	fills, strokes, extras = spec
	fill_paths: list[str] = []
	cap_offset = 14.0
	for group in list(fills) + list(extras):
		if len(group) < 3:
			continue
		fill_paths.append(fill_path(place_cap(group, GAP_X0, False), MATTE_RED))
		fill_paths.append(fill_path(place_cap(group, GAP_X1, True), MATTE_RED))

	for seg in strokes:
		if len(seg) < 2:
			continue
		w = max(p[0] for p in seg)
		left = translate_pts(seg, GAP_X0 - w - cap_offset, 0)
		right = translate_pts([(w - x, y) for x, y in seg], GAP_X1 + cap_offset, 0)
		fill_paths.append(stroke_path(left, MATTE_RED))
		fill_paths.append(stroke_path(right, MATTE_RED))

	runner_paths = [
		stroke_path(
			[(MARGIN_L, RUNNER_Y), (GAP_X0, RUNNER_Y)],
			MATTE_RED,
		),
		stroke_path(
			[(GAP_X1, RUNNER_Y), (MARGIN_R, RUNNER_Y)],
			MATTE_RED,
		),
	]

	body = "\n  ".join(fill_paths + runner_paths)
	return (
		'<?xml version="1.0" encoding="UTF-8"?>\n'
		f'<svg width="950" height="35" viewBox="0 0 950 35" fill="none" '
		f'xmlns="http://www.w3.org/2000/svg">\n'
		f"  {body}\n"
		f"</svg>\n"
	)


def main() -> int:
	out_dir = Path(__file__).resolve().parent.parent / "public/assets/lines"
	out_dir.mkdir(parents=True, exist_ok=True)
	start = 102
	for i, spec in enumerate(CAP_MODELS):
		num = start + i
		svg = build_line_svg(spec)
		dest = out_dir / f"type_{num}.svg"
		dest.write_text(svg, encoding="utf-8")
		print(f"OK type_{num}.svg")
	if len(CAP_MODELS) != 20:
		raise SystemExit(f"expected 20 models, got {len(CAP_MODELS)}")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
