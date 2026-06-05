#!/usr/bin/env python3
"""Verify browser export matrix matches design→mm layout (no Fabric required)."""
import re
import sys

DW, DH = 3651, 2132
MM_W, MM_H = 525, 145
BG_L, BG_T, BG_W, BG_H = 75, 50, 900, 526
SX = MM_W / BG_W
SY = MM_H / BG_H
TX = -BG_L * SX
TY = -BG_T * SY

REFS = {
    "text": (1136 + 696, 1256 + 46.5),
    "iconLeft": (817.5 + 40.5, 1243 + 57),
    "lines": (609 + 1212, 1173 + 127),
}


def design_center_to_mm(cx_design, cy_design):
    canvas_x = BG_L + cx_design * (BG_W / DW)
    canvas_y = BG_T + cy_design * (BG_H / DH)
    mm_x = SX * canvas_x + TX
    mm_y_top = SY * canvas_y + TY
    return mm_x, mm_y_top


def parse_svg_matrix(svg):
    m = re.search(
        r'id="pckz-engrave"[^>]*transform="matrix\(([^)]+)\)"', svg, re.I
    )
    if not m:
        return None
    nums = [float(x) for x in m.group(1).split()]
    return nums[:6] if len(nums) >= 6 else None


def apply_matrix(m, x, y):
    return m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]


def main():
    expected_matrix = [SX, 0, 0, SY, TX, TY]
    for name, (cx, cy) in REFS.items():
        mm_x, mm_y = design_center_to_mm(cx, cy)
        canvas_x = BG_L + cx * (BG_W / DW)
        canvas_y = BG_T + cy * (BG_H / DH)
        out_x, out_y = apply_matrix(expected_matrix, canvas_x, canvas_y)
        if abs(out_x - mm_x) > 0.01 or abs(out_y - mm_y) > 0.01:
            print(f"FAIL: {name} matrix mismatch")
            sys.exit(1)
    print("OK: export matrix matches design→mm layout for text/icons/lines centers")
    print(f"OK: matrix({SX:.6f} 0 0 {SY:.6f} {TX:.3f} {TY:.3f})")


if __name__ == "__main__":
    main()
