#!/usr/bin/env python3
"""
Generate bundled line ornaments type_92–type_101 (950×35 white SVG paths).
Reference sheet models 1–10: same artboard/runners as type_82–91, distinct type slugs.
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


def runners() -> list[str]:
	y = RUNNER_Y
	return [
		f'M{fmt(MARGIN_L)} {fmt(y)} L{fmt(GAP_X0)} {fmt(y)}',
		f'M{fmt(GAP_X1)} {fmt(y)} L{fmt(MARGIN_R)} {fmt(y)}',
	]


def cap_fleur() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(5, CY - 12),
		(11, CY - 5),
		(16, CY - 13),
		(22, CY - 3),
		(28, CY - 10),
		(34, CY - 1),
		(40, CY - 8),
		(46, CY),
		(40, CY + 8),
		(34, CY + 1),
		(28, CY + 10),
		(22, CY + 3),
		(16, CY + 13),
		(11, CY + 5),
		(5, CY + 12),
	]


def cap_fleur_bulb() -> list[tuple[float, float]]:
	return circle_pts(46, CY, 3.2)


def cap_leaves() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(48, CY)]
	for dx, dy in [(42, -9), (36, -12), (30, -8), (24, -11), (18, -7), (12, -10), (6, -7)]:
		pts.extend([(dx, CY + dy), (dx - 4, CY + dy * 0.45), (dx + 2, CY)])
	pts.append((0, CY))
	for dx, dy in [(6, -8), (12, -11), (18, -7)]:
		pts.extend([(dx, CY + dy), (dx + 3, CY + dy * 0.4)])
	pts.append((48, CY))
	return pts


def cap_scroll() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(4, CY - 5),
		(8, CY - 9),
		(14, CY - 11),
		(20, CY - 8),
		(26, CY - 12),
		(32, CY - 6),
		(38, CY - 10),
		(42, CY - 3),
		(46, CY - 7),
		(50, CY),
		(46, CY + 7),
		(42, CY + 3),
		(38, CY + 10),
		(32, CY + 6),
		(26, CY + 12),
		(20, CY + 8),
		(14, CY + 11),
		(8, CY + 9),
		(4, CY + 5),
	]


def cap_scroll_arrow() -> list[tuple[float, float]]:
	return [(0, CY - 3), (5, CY), (0, CY + 3)]


def cap_lotus() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for angle in range(160, 24, -17):
		rad = math.radians(angle)
		pts.append((8 + 36 * math.cos(rad), CY + 13 * math.sin(rad) * 0.6))
	pts.append((48, CY))
	for angle in range(24, 161, 17):
		rad = math.radians(angle)
		pts.append((8 + 36 * math.cos(rad), CY - 13 * math.sin(rad) * 0.6))
	return pts


def cap_lotus_dot() -> list[tuple[float, float]]:
	return circle_pts(52, CY, 2.3)


def cap_filigree() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(4, CY - 8),
		(9, CY - 12),
		(14, CY - 7),
		(18, CY - 13),
		(24, CY - 5),
		(30, CY - 11),
		(36, CY - 4),
		(42, CY - 9),
		(48, CY - 3),
		(52, CY),
		(48, CY + 3),
		(42, CY + 9),
		(36, CY + 4),
		(30, CY + 11),
		(24, CY + 5),
		(18, CY + 13),
		(14, CY + 7),
		(9, CY + 12),
		(4, CY + 8),
	]


def cap_filigree_diamond() -> list[tuple[float, float]]:
	return [(52, CY - 3.5), (56.5, CY), (52, CY + 3.5)]


def scale_pts(points: list[tuple[float, float]], sx: float, sy: float) -> list[tuple[float, float]]:
	return [(x * sx, y * sy) for x, y in points]


def translate_pts(points: list[tuple[float, float]], ox: float, oy: float) -> list[tuple[float, float]]:
	return [(x + ox, y + oy) for x, y in points]


def cap_tribal() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(11, CY - 13),
		(24, CY - 4),
		(20, CY - 13),
		(36, CY),
		(20, CY + 13),
		(24, CY + 4),
		(11, CY + 13),
	]


def cap_fan() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(10, CY)]
	for i in range(5):
		a = math.radians(58 + i * 16)
		pts.append((10 + 36 * math.cos(a), CY + 15 * math.sin(a - math.pi / 2)))
	pts.append((50, CY))
	for i in range(4, -1, -1):
		a = math.radians(58 + i * 16)
		pts.append((10 + 36 * math.cos(a), CY - 15 * math.sin(a - math.pi / 2)))
	return pts


def cap_fan_hub() -> list[tuple[float, float]]:
	return circle_pts(10, CY, 2.6)


def cap_chevrons() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for depth, spread in [(5, 7), (12, 12), (19, 16), (26, 19), (33, 20)]:
		pts.extend([(depth, CY - spread), (depth + 9, CY), (depth, CY + spread)])
	pts.append((50, CY))
	for depth, spread in [(33, 20), (26, 19), (19, 16), (12, 12), (5, 7)]:
		pts.extend([(depth, CY + spread), (depth - 9, CY), (depth, CY - spread)])
	return pts


def cap_celtic() -> list[tuple[float, float]]:
	return [
		(0, CY),
		(7, CY - 11),
		(16, CY - 7),
		(22, CY - 13),
		(30, CY - 5),
		(38, CY - 11),
		(44, CY - 3),
		(50, CY),
		(44, CY + 3),
		(38, CY + 11),
		(30, CY + 5),
		(22, CY + 13),
		(16, CY + 7),
		(7, CY + 11),
	]


def cap_celtic_loop() -> list[tuple[float, float]]:
	return circle_pts(54, CY, 3.4)


def cap_fletching() -> list[tuple[float, float]]:
	pts: list[tuple[float, float]] = [(0, CY)]
	for i, (y0, y1) in enumerate([(-11, -7), (-7, -4), (-3, -1), (1, 2), (5, 5), (9, 8)]):
		x0 = 5 + i * 7
		pts.extend([(x0, CY + y0), (x0 + 9, CY + y1), (x0 + 3, CY)])
	pts.append((50, CY))
	for i, (y0, y1) in enumerate(reversed([(-11, -7), (-7, -4), (-3, -1), (1, 2), (5, 5), (9, 8)])):
		x0 = 5 + i * 7
		pts.extend([(x0, CY - y0), (x0 + 9, CY - y1), (x0 + 3, CY)])
	return pts


def cap_fletching_diamond() -> list[tuple[float, float]]:
	return [(52, CY - 4), (57, CY), (52, CY + 4)]


CAP_BUILDERS: list[tuple[str, callable, list[callable]]] = [
	("fleur", cap_fleur, [cap_fleur_bulb]),
	("leaves", cap_leaves, []),
	("scroll", cap_scroll, [cap_scroll_arrow]),
	("lotus", cap_lotus, [cap_lotus_dot]),
	("filigree", cap_filigree, [cap_filigree_diamond]),
	("tribal", cap_tribal, []),
	("fan", cap_fan, [cap_fan_hub]),
	("chevrons", cap_chevrons, []),
	("celtic", cap_celtic, [cap_celtic_loop]),
	("fletching", cap_fletching, [cap_fletching_diamond]),
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
	start = 92
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
