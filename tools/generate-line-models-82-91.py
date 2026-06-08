#!/usr/bin/env python3
"""
Generate bundled line ornaments type_82–type_91 (950×35 white SVG paths).
Vector silhouettes modeled after the decorative reference sheet (no raster).
"""

from __future__ import annotations

import math
from pathlib import Path

CANVAS_W = 950.0
CANVAS_H = 35.0
CY = 17.5
BAR_H = 2.2
# Text clearance band (matches Cloudlift / bundled line proportions).
GAP_X0 = 352.0
GAP_X1 = 598.0
MARGIN_L = 9.5
MARGIN_R = 940.5
RUNNER_Y = CY


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


def mirror_pts(points: list[tuple[float, float]], axis: float) -> list[tuple[float, float]]:
	return [(2 * axis - x, y) for x, y in reversed(points)]


def translate_pts(points: list[tuple[float, float]], ox: float, oy: float) -> list[tuple[float, float]]:
	return [(x + ox, y + oy) for x, y in points]


def scale_pts(points: list[tuple[float, float]], sx: float, sy: float) -> list[tuple[float, float]]:
	return [(x * sx, y * sy) for x, y in points]


def runners() -> list[str]:
	y = RUNNER_Y
	return [
		f'M{fmt(MARGIN_L)} {fmt(y)} L{fmt(GAP_X0)} {fmt(y)}',
		f'M{fmt(GAP_X1)} {fmt(y)} L{fmt(MARGIN_R)} {fmt(y)}',
	]


def cap_fleur() -> list[tuple[float, float]]:
	# Fleur-de-lis (tip x=0, base x=46).
	return [
		(0, CY),
		(6, CY - 11),
		(12, CY - 5),
		(17, CY - 13),
		(23, CY - 3),
		(29, CY - 10),
		(35, CY - 1),
		(41, CY - 8),
		(46, CY),
		(41, CY + 8),
		(35, CY + 1),
		(29, CY + 10),
		(23, CY + 3),
		(17, CY + 13),
		(12, CY + 5),
		(6, CY + 11),
	]


def cap_leaves() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(44, CY)]
	for i, (dx, dy) in enumerate([(38, -8), (32, -11), (26, -7), (20, -10), (14, -6)]):
		pts.extend([(dx, CY + dy), (dx - 5, CY + dy * 0.55), (dx + 2, CY)])
	pts.append((0, CY))
	for dx, dy in [(6, -9), (12, -12), (18, -8)]:
		pts.extend([(dx, CY + dy), (dx + 4, CY + dy * 0.5)])
	pts.append((44, CY))
	return pts


def cap_scroll() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(6, CY - 6),
		(12, CY - 10),
		(18, CY - 8),
		(24, CY - 12),
		(30, CY - 5),
		(36, CY - 9),
		(40, CY - 2),
		(44, CY - 6),
		(48, CY),
		(44, CY + 6),
		(40, CY + 2),
		(36, CY + 9),
		(30, CY + 5),
		(24, CY + 12),
		(18, CY + 8),
		(12, CY + 10),
		(6, CY + 6),
	]


def cap_lotus() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for angle in range(155, 26, -18):
		rad = math.radians(angle)
		pts.append((10 + 34 * math.cos(rad), CY + 12 * math.sin(rad) * 0.62))
	pts.append((46, CY))
	for angle in range(25, 156, 18):
		rad = math.radians(angle)
		pts.append((10 + 34 * math.cos(rad), CY - 12 * math.sin(rad) * 0.62))
	return pts


def cap_lotus_dot() -> list[tuple[float, float]]:
	r = 2.2
	cx, cy = 50, CY
	return [(cx + r, cy), (cx, cy + r), (cx - r, cy), (cx, cy - r)]


def cap_filigree() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(5, CY - 7),
		(10, CY - 11),
		(16, CY - 6),
		(20, CY - 12),
		(26, CY - 4),
		(32, CY - 10),
		(38, CY - 3),
		(44, CY - 8),
		(50, CY),
		(46, CY + 4),
		(44, CY + 8),
		(38, CY + 3),
		(32, CY + 10),
		(26, CY + 4),
		(20, CY + 12),
		(16, CY + 6),
		(10, CY + 11),
		(5, CY + 7),
	]


def cap_tribal() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(10, CY - 12),
		(22, CY - 4),
		(18, CY - 12),
		(30, CY),
		(22, CY + 12),
		(18, CY + 4),
		(10, CY + 12),
	]


def cap_fan() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(8, CY)]
	for i in range(6):
		a = math.radians(55 + i * 14)
		pts.append((8 + 38 * math.cos(a), CY + 16 * math.sin(a - math.pi / 2)))
	pts.append((48, CY))
	for i in range(5, -1, -1):
		a = math.radians(55 + i * 14)
		pts.append((8 + 38 * math.cos(a), CY - 16 * math.sin(a - math.pi / 2)))
	return pts


def cap_fan_hub() -> list[tuple[float, float]]:
	# Small circle at bar end.
	r = 2.8
	cx, cy = 8, CY
	return [
		(cx + r, cy),
		(cx, cy + r),
		(cx - r, cy),
		(cx, cy - r),
	]


def cap_chevrons() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for depth, spread in [(6, 8), (14, 14), (22, 18), (30, 20)]:
		pts.extend(
			[
				(depth, CY - spread),
				(depth + 8, CY),
				(depth, CY + spread),
			]
		)
	pts.append((48, CY))
	for depth, spread in [(30, 20), (22, 18), (14, 14), (6, 8)]:
		pts.extend([(depth, CY + spread), (depth - 8, CY), (depth, CY - spread)])
	return pts


def cap_celtic() -> list[tuple[float, float]]:
	# Simplified knot loop + outer ring segment.
	return [
		(0, CY),
		(8, CY - 10),
		(18, CY - 6),
		(24, CY - 12),
		(32, CY - 4),
		(40, CY - 10),
		(46, CY - 2),
		(50, CY),
		(46, CY + 2),
		(40, CY + 10),
		(32, CY + 4),
		(24, CY + 12),
		(18, CY + 6),
		(8, CY + 10),
	]


def cap_celtic_ring() -> list[tuple[float, float]]:
	r = 3.2
	cx, cy = 52, CY
	return [(cx + r, cy), (cx, cy + r), (cx - r, cy), (cx, cy - r)]


def cap_fletching() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i, yoff in enumerate([(-10, -6), (-6, -3), (-2, 0), (2, 3), (6, 6)]):
		x0 = 6 + i * 7
		pts.extend([(x0, CY + yoff[0]), (x0 + 10, CY + yoff[1]), (x0 + 4, CY)])
	pts.append((48, CY))
	for i, yoff in enumerate(reversed([(-10, -6), (-6, -3), (-2, 0), (2, 3), (6, 6)])):
		x0 = 6 + i * 7
		pts.extend([(x0, CY - yoff[0]), (x0 + 10, CY - yoff[1]), (x0 + 4, CY)])
	return pts


def cap_diamond_tip() -> list[tuple[float, float]]:
	return [(50, CY - 4), (54, CY), (50, CY + 4)]


CAP_BUILDERS: list[tuple[str, callable, list[callable]]] = [
	("fleur", cap_fleur, []),
	("leaves", cap_leaves, []),
	("scroll", cap_scroll, []),
	("lotus", cap_lotus, [cap_lotus_dot]),
	("filigree", cap_filigree, [cap_diamond_tip]),
	("tribal", cap_tribal, []),
	("fan", cap_fan, [cap_fan_hub]),
	("chevrons", cap_chevrons, []),
	("celtic", cap_celtic, [cap_celtic_ring]),
	("fletching", cap_fletching, [cap_diamond_tip]),
]


def flip_cap(points: list[tuple[float, float]]) -> list[tuple[float, float]]:
	w = max(p[0] for p in points)
	return [(w - x, y) for x, y in points]


def build_line_svg(cap_main: list[tuple[float, float]], extras: list[list[tuple[float, float]]]) -> str:
	w = max(p[0] for p in cap_main)
	left = translate_pts(cap_main, GAP_X0 - w, 0)
	paths: list[str] = [pts_to_d(left)]
	for extra in extras:
		ew = max(p[0] for p in extra)
		ep = translate_pts(extra, GAP_X0 - ew - 4, 0)
		paths.append(pts_to_d(ep))
	right = translate_pts(flip_cap(cap_main), GAP_X1, 0)
	paths.append(pts_to_d(right))
	for extra in extras:
		ew = max(p[0] for p in extra)
		er = translate_pts(flip_cap(extra), GAP_X1 + 4, 0)
		paths.append(pts_to_d(er))
	for runner in runners():
		paths.append(
			f'<path d="{runner}" fill="none" stroke="white" stroke-width="1.2" '
			f'stroke-linecap="round" stroke-linejoin="round"/>'
		)
	fill_paths = "\n  ".join(
		f'<path d="{d}" fill-rule="evenodd" clip-rule="evenodd" fill="white" stroke="none"/>'
		for d in paths[: len(paths) - 2]
	)
	return (
		'<?xml version="1.0" encoding="UTF-8"?>\n'
		f'<svg width="950" height="35" viewBox="0 0 950 35" fill="none" '
		f'xmlns="http://www.w3.org/2000/svg">\n'
		f"  {fill_paths}\n"
		f"  {paths[-2]}\n"
		f"  {paths[-1]}\n"
		f"</svg>\n"
	)


def main() -> int:
	out_dir = Path(__file__).resolve().parent.parent / "public/assets/lines"
	out_dir.mkdir(parents=True, exist_ok=True)
	start = 82
	for i, (_name, main_fn, extra_fns) in enumerate(CAP_BUILDERS):
		num = start + i
		extras = [fn() for fn in extra_fns]
		svg = build_line_svg(main_fn(), extras)
		dest = out_dir / f"type_{num}.svg"
		dest.write_text(svg, encoding="utf-8")
		print(f"OK type_{num}.svg")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
