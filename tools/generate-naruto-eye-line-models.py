#!/usr/bin/env python3
"""
Generate bundled Naruto anime eye line models type_102–type_111 (950×35, preserve colors).
Source artwork definitions match the customer reference sheet (10 models only).
"""

from __future__ import annotations

import math
from pathlib import Path

CANVAS_W = 950.0
CANVAS_H = 35.0
LEFT_CX = 385.0
RIGHT_CX = 565.0
CY = 17.5

BLACK = "#000000"
WHITE = "#FFFFFF"
SHARINGAN_RED = "#E02020"
RINNEGAN_PURPLE = "#B8A0D0"
BYAKUGAN_LAVENDER = "#E8E0F8"
SAGE_YELLOW = "#F0C020"
SAGE_ORANGE = "#E87818"
TENSEIGAN_BLUE = "#40C8F0"
KETSURYUGAN_RED = "#C01818"
KETSURYUGAN_DARK = "#601010"

MODELS = [
	(102, "Sharingan (3 Tomoe)", "sharingan_3_tomoe"),
	(103, "Mangekyo Sharingan", "mangekyo_sharingan"),
	(104, "Rinnegan", "rinnegan"),
	(105, "Mangekyo (Itachi)", "mangekyo_itachi"),
	(106, "Rinnegan (Tomoe)", "rinnegan_tomoe"),
	(107, "Byakugan", "byakugan"),
	(108, "Sage Mode", "sage_mode"),
	(109, "Tenseigan", "tenseigan"),
	(110, "Ketsuryugan", "ketsuryugan"),
	(111, "Eien no Mangekyo Sharingan", "eien_mangekyo"),
]


def fmt(n: float) -> str:
	return f"{n:.4f}".rstrip("0").rstrip(".")


def eye_outline(cx: float, cy: float, w: float = 42.0, h: float = 22.0) -> str:
	"""Sharp angular anime eye shape."""
	x0, x1 = cx - w / 2, cx + w / 2
	y0, y1 = cy - h / 2, cy + h / 2
	inner = h * 0.35
	return (
		f"M{fmt(x0 + w * 0.15)} {fmt(y0 + h * 0.25)} "
		f"L{fmt(x0 + w * 0.08)} {fmt(cy)} "
		f"L{fmt(x0 + w * 0.18)} {fmt(y1 - h * 0.2)} "
		f"L{fmt(x1 - w * 0.12)} {fmt(y1 - h * 0.08)} "
		f"L{fmt(x1 - w * 0.05)} {fmt(cy)} "
		f"L{fmt(x1 - w * 0.14)} {fmt(y0 + h * 0.12)} "
		f"L{fmt(x0 + w * 0.22)} {fmt(y0 + inner)} Z"
	)


def circle(cx: float, cy: float, r: float) -> str:
	return (
		f"M{fmt(cx - r)} {fmt(cy)} "
		f"A{fmt(r)} {fmt(r)} 0 1 0 {fmt(cx + r)} {fmt(cy)} "
		f"A{fmt(r)} {fmt(r)} 0 1 0 {fmt(cx - r)} {fmt(cy)} Z"
	)


def tomoe(cx: float, cy: float, angle_deg: float, scale: float = 1.0) -> str:
	"""Comma-shaped tomoe rotated around angle_deg."""
	r = 3.2 * scale
	a = math.radians(angle_deg)
	px = cx + math.cos(a) * 5.5 * scale
	py = cy + math.sin(a) * 5.5 * scale
	# Comma blob
	c1x = px + math.cos(a + 0.8) * r * 1.2
	c1y = py + math.sin(a + 0.8) * r * 1.2
	c2x = px + math.cos(a - 0.5) * r * 0.8
	c2y = py + math.sin(a - 0.5) * r * 0.8
	tail_x = px + math.cos(a + math.pi) * r * 1.8
	tail_y = py + math.sin(a + math.pi) * r * 1.8
	return (
		f"M{fmt(px)} {fmt(py)} "
		f"C{fmt(c1x)} {fmt(c1y)} {fmt(c2x)} {fmt(c2y)} {fmt(tail_x)} {fmt(tail_y)} "
		f"C{fmt(c2x + math.cos(a) * r)} {fmt(c2y + math.sin(a) * r)} "
		f"{fmt(c1x - math.cos(a) * r * 0.5)} {fmt(c1y - math.sin(a) * r * 0.5)} "
		f"{fmt(px)} {fmt(py)} Z"
	)


def path_el(fill: str, d: str, stroke: str = "none", sw: float = 0) -> str:
	s = f' fill="{fill}" stroke="{stroke}"'
	if sw > 0:
		s += f' stroke-width="{fmt(sw)}" stroke-linejoin="round" stroke-linecap="round"'
	return f'<path d="{d}" fill-rule="evenodd" clip-rule="evenodd"{s}/>'


def pair_paths(left_parts: list[str], right_parts: list[str]) -> list[str]:
	"""Mirror right eye parts horizontally around canvas center."""
	out = list(left_parts)
	mirror_x = CANVAS_W / 2
	for part in right_parts:
		# Simple mirror: duplicate left with cx offset
		out.append(part)
	return out


def eye_pair(left_fn, right_fn=None) -> list[str]:
	if right_fn is None:
		right_fn = left_fn
	return left_fn(LEFT_CX, CY) + right_fn(RIGHT_CX, CY)


def sharingan_3_tomoe(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(SHARINGAN_RED, circle(cx, cy, 9.5)))
	parts.append(path_el(BLACK, circle(cx, cy, 2.8)))
	for angle in (30, 150, 270):
		parts.append(path_el(BLACK, tomoe(cx, cy, angle, 1.0)))
	return parts


def mangekyo_sharingan(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(SHARINGAN_RED, circle(cx, cy, 10.5)))
	# Three curved pinwheel blades
	for i in range(3):
		a = math.radians(i * 120 - 90)
		a2 = a + math.radians(55)
		a3 = a + math.radians(110)
		r0, r1, r2 = 2.5, 10.0, 10.0
		x0, y0 = cx + math.cos(a) * r0, cy + math.sin(a) * r0
		x1, y1 = cx + math.cos(a2) * r1, cy + math.sin(a2) * r1
		x2, y2 = cx + math.cos(a3) * r2, cy + math.sin(a3) * r2
		d = (
			f"M{fmt(x0)} {fmt(y0)} "
			f"Q{fmt(x1)} {fmt(y1)} {fmt(x2)} {fmt(y2)} "
			f"Q{fmt(cx + math.cos(a + math.radians(70)) * 3)} "
			f"{fmt(cy + math.sin(a + math.radians(70)) * 3)} "
			f"{fmt(x0)} {fmt(y0)} Z"
		)
		parts.append(path_el(BLACK, d))
	parts.append(path_el(BLACK, circle(cx, cy, 2.2)))
	return parts


def rinnegan(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(RINNEGAN_PURPLE, circle(cx, cy, 10.5)))
	for r in (2.2, 4.2, 6.2, 8.2, 10.0):
		parts.append(
			path_el("none", circle(cx, cy, r), BLACK, 0.55 if r < 10 else 0.7)
		)
	parts.append(path_el(BLACK, circle(cx, cy, 1.8)))
	return parts


def mangekyo_itachi(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(SHARINGAN_RED, circle(cx, cy, 10.5)))
	for i in range(3):
		a = math.radians(i * 120 - 90)
		x1 = cx + math.cos(a) * 10.5
		y1 = cy + math.sin(a) * 10.5
		x2 = cx + math.cos(a + math.radians(120)) * 10.5
		y2 = cy + math.sin(a + math.radians(120)) * 10.5
		d = f"M{fmt(cx)} {fmt(cy)} L{fmt(x1)} {fmt(y1)} L{fmt(x2)} {fmt(y2)} Z"
		parts.append(path_el(BLACK, d))
	parts.append(path_el(BLACK, circle(cx, cy, 2.0)))
	return parts


def rinnegan_tomoe(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(RINNEGAN_PURPLE, circle(cx, cy, 10.5)))
	for r in (2.5, 5.0, 7.5, 9.8):
		parts.append(path_el("none", circle(cx, cy, r), BLACK, 0.5))
	parts.append(path_el(BLACK, circle(cx, cy, 1.8)))
	for angle in (0, 120, 240):
		parts.append(path_el(BLACK, tomoe(cx, cy, angle, 0.75)))
	for angle in (60, 180, 300):
		parts.append(path_el(BLACK, tomoe(cx + math.cos(math.radians(angle)) * 3.5,
		                                 cy + math.sin(math.radians(angle)) * 3.5, angle + 180, 0.55)))
	return parts


def byakugan(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.2))
	parts.append(path_el(BYAKUGAN_LAVENDER, circle(cx, cy, 10.0)))
	parts.append(path_el(WHITE, circle(cx, cy, 4.5)))
	for i in range(8):
		a = math.radians(i * 45)
		x1 = cx + math.cos(a) * 2.0
		y1 = cy + math.sin(a) * 2.0
		x2 = cx + math.cos(a) * 8.5
		y2 = cy + math.sin(a) * 8.5
		parts.append(path_el("none", f"M{fmt(x1)} {fmt(y1)} L{fmt(x2)} {fmt(y2)}", "#D0C8E8", 0.35))
	return parts


def sage_mode(cx: float, cy: float) -> list[str]:
	parts = []
	# Orange eyelid markings
	ey = cy - 11
	parts.append(path_el(SAGE_ORANGE, (
		f"M{fmt(cx - 20)} {fmt(ey + 4)} "
		f"Q{fmt(cx)} {fmt(ey)} {fmt(cx + 20)} {fmt(ey + 4)} "
		f"L{fmt(cx + 18)} {fmt(ey + 7)} "
		f"Q{fmt(cx)} {fmt(ey + 3)} {fmt(cx - 18)} {fmt(ey + 7)} Z"
	)))
	parts.append(path_el(SAGE_ORANGE, (
		f"M{fmt(cx - 18)} {fmt(cy + 11)} "
		f"Q{fmt(cx)} {fmt(cy + 14)} {fmt(cx + 18)} {fmt(cy + 11)} "
		f"L{fmt(cx + 16)} {fmt(cy + 8)} "
		f"Q{fmt(cx)} {fmt(cy + 11)} {fmt(cx - 16)} {fmt(cy + 8)} Z"
	)))
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(SAGE_YELLOW, circle(cx, cy, 9.5)))
	# Horizontal toad pupil
	parts.append(path_el(BLACK, (
		f"M{fmt(cx - 5.5)} {fmt(cy - 1.2)} "
		f"L{fmt(cx + 5.5)} {fmt(cy - 1.2)} "
		f"L{fmt(cx + 5.5)} {fmt(cy + 1.2)} "
		f"L{fmt(cx - 5.5)} {fmt(cy + 1.2)} Z"
	)))
	return parts


def tenseigan(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(TENSEIGAN_BLUE, circle(cx, cy, 10.5)))
	parts.append(path_el(WHITE, circle(cx, cy, 3.5)))
	for r in (5.5, 7.5, 9.5):
		parts.append(path_el("none", circle(cx, cy, r), "#80D8F8", 0.45))
	# Floral petals
	for i in range(6):
		a = math.radians(i * 60)
		x1 = cx + math.cos(a) * 4.0
		y1 = cy + math.sin(a) * 4.0
		x2 = cx + math.cos(a) * 8.5
		y2 = cy + math.sin(a) * 8.5
		d = (
			f"M{fmt(cx)} {fmt(cy)} "
			f"Q{fmt(x1)} {fmt(y1)} {fmt(x2)} {fmt(y2)} "
			f"Q{fmt(x1 * 0.7 + cx * 0.3)} {fmt(y1 * 0.7 + cy * 0.3)} "
			f"{fmt(cx)} {fmt(cy)} Z"
		)
		parts.append(path_el("#A0E8FF", d))
	parts.append(path_el(WHITE, circle(cx, cy, 2.0)))
	return parts


def ketsuryugan(cx: float, cy: float) -> list[str]:
	parts = []
	# Dark red eyelid markings
	parts.append(path_el(KETSURYUGAN_DARK, (
		f"M{fmt(cx - 20)} {fmt(cy - 12)} "
		f"Q{fmt(cx)} {fmt(cy - 15)} {fmt(cx + 20)} {fmt(cy - 12)} "
		f"L{fmt(cx + 18)} {fmt(cy - 9)} "
		f"Q{fmt(cx)} {fmt(cy - 11)} {fmt(cx - 18)} {fmt(cy - 9)} Z"
	)))
	parts.append(path_el(KETSURYUGAN_DARK, (
		f"M{fmt(cx - 18)} {fmt(cy + 12)} "
		f"Q{fmt(cx)} {fmt(cy + 15)} {fmt(cx + 18)} {fmt(cy + 12)} "
		f"L{fmt(cx + 16)} {fmt(cy + 9)} "
		f"Q{fmt(cx)} {fmt(cy + 11)} {fmt(cx - 16)} {fmt(cy + 9)} Z"
	)))
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(KETSURYUGAN_RED, circle(cx, cy, 9.5)))
	parts.append(path_el("#801010", circle(cx, cy, 2.5)))
	parts.append(path_el(KETSURYUGAN_DARK, (
		f"M{fmt(cx - 6)} {fmt(cy - 0.8)} L{fmt(cx + 6)} {fmt(cy - 0.8)} "
		f"L{fmt(cx + 6)} {fmt(cy + 0.8)} L{fmt(cx - 6)} {fmt(cy + 0.8)} Z"
	)))
	return parts


def eien_mangekyo(cx: float, cy: float) -> list[str]:
	parts = []
	parts.append(path_el(WHITE, eye_outline(cx, cy), BLACK, 1.4))
	parts.append(path_el(SHARINGAN_RED, circle(cx, cy, 10.5)))
	# Outer curved pinwheel (Mangekyo Sharingan layer)
	for i in range(3):
		a = math.radians(i * 120 - 90)
		a2 = a + math.radians(55)
		a3 = a + math.radians(110)
		x0, y0 = cx + math.cos(a) * 2.5, cy + math.sin(a) * 2.5
		x2, y2 = cx + math.cos(a3) * 10.0, cy + math.sin(a3) * 10.0
		x1, y1 = cx + math.cos(a2) * 10.0, cy + math.sin(a2) * 10.0
		d = (
			f"M{fmt(x0)} {fmt(y0)} "
			f"Q{fmt(x1)} {fmt(y1)} {fmt(x2)} {fmt(y2)} "
			f"Q{fmt(cx + math.cos(a + math.radians(70)) * 3)} "
			f"{fmt(cy + math.sin(a + math.radians(70)) * 3)} "
			f"{fmt(x0)} {fmt(y0)} Z"
		)
		parts.append(path_el(BLACK, d))
	# Inner three-pointed star (Itachi layer)
	for i in range(3):
		a = math.radians(i * 120 - 90)
		x1 = cx + math.cos(a) * 7.0
		y1 = cy + math.sin(a) * 7.0
		x2 = cx + math.cos(a + math.radians(120)) * 7.0
		y2 = cy + math.sin(a + math.radians(120)) * 7.0
		d = f"M{fmt(cx)} {fmt(cy)} L{fmt(x1)} {fmt(y1)} L{fmt(x2)} {fmt(y2)} Z"
		parts.append(path_el(BLACK, d))
	parts.append(path_el(BLACK, circle(cx, cy, 2.0)))
	return parts


BUILDERS = {
	"sharingan_3_tomoe": sharingan_3_tomoe,
	"mangekyo_sharingan": mangekyo_sharingan,
	"rinnegan": rinnegan,
	"mangekyo_itachi": mangekyo_itachi,
	"rinnegan_tomoe": rinnegan_tomoe,
	"byakugan": byakugan,
	"sage_mode": sage_mode,
	"tenseigan": tenseigan,
	"ketsuryugan": ketsuryugan,
	"eien_mangekyo": eien_mangekyo,
}


def build_svg(builder_key: str) -> str:
	builder = BUILDERS[builder_key]
	left = builder(LEFT_CX, CY)
	right = builder(RIGHT_CX, CY)
	body = "\n  ".join(left + right)
	return (
		'<?xml version="1.0" encoding="UTF-8"?>\n'
		f'<svg width="950" height="35" viewBox="0 0 950 35" fill="none" '
		f'xmlns="http://www.w3.org/2000/svg">\n'
		f"  {body}\n"
		f"</svg>\n"
	)


def main() -> int:
	root = Path(__file__).resolve().parent.parent
	dest = root / "public/assets/lines"
	dest.mkdir(parents=True, exist_ok=True)
	for num, label, key in MODELS:
		svg = build_svg(key)
		out = dest / f"type_{num}.svg"
		out.write_text(svg, encoding="utf-8")
		print(f"OK type_{num}.svg — {label} ({len(svg)} bytes)")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
