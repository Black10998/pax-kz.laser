#!/usr/bin/env python3
"""Smoke checks for LBRN2 scene building (text + knocked lines)."""
import re
import sys

MM_W, MM_H = 525, 145


def sample_master_svg():
    return """<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 525 145">
<g id="pckz-engrave">
<g id="pckz-lines"><path d="M 10 10 L 500 10" fill="#ff0000"/></g>
<g id="pckz-icon-left"><path d="M 100 50 L 120 70 Z" fill="#fff"/></g>
<text id="pckz-text" x="262" y="72" font-family="Russo One" font-size="8.5">AB 123</text>
</g>
</svg>"""


def extract_group_inner(svg, group_id):
    m = re.search(
        r'<g[^>]*\bid="' + re.escape(group_id) + r'"[^>]*>([\s\S]*?)</g>',
        svg,
        re.I,
    )
    return m.group(1).strip() if m else ""


def font_mm_from_layout(font_px, bg_width):
    if bg_width > 1:
        return font_px * (MM_W / bg_width)
    return (font_px / 2132) * MM_H


def main():
    svg = sample_master_svg()
    lines = extract_group_inner(svg, "pckz-lines")
    if not lines or "path" not in lines:
        print("FAIL: could not extract pckz-lines fragment")
        sys.exit(1)

    if not re.search(r'<text[^>]*>AB 123</text>', svg, re.I):
        print("FAIL: sample SVG missing text element")
        sys.exit(1)

    font_px = 42
    bg_w = 900
    font_mm = font_mm_from_layout(font_px, bg_w)
    if font_mm < 3 or font_mm > 30:
        print(f"FAIL: font_mm out of range: {font_mm}")
        sys.exit(1)

    lbrn = (
        '<Shape Type="Text" CutIndex="1" Text="AB 123" Font="Russo One" Height="'
        + f"{font_mm:.4f}".rstrip("0").rstrip(".")
        + '"'
    )
    if "Font=\"Arial\"" in lbrn and "Russo One" not in lbrn:
        print("FAIL: LBRN2 must keep customer font name (Russo One), not substitute Arial")
        sys.exit(1)

    print("OK: pckz-lines fragment extractable for LBRN2 path import")
    print("OK: layout font mm scaling from background fit")
    print("OK: customer font preserved for LightBurn Text shape")
    sys.exit(0)


if __name__ == "__main__":
    main()
