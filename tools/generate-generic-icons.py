#!/usr/bin/env python3
"""Generate bundled generic tintable icons (24x24, white/black SVG pairs)."""

from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "images" / "icons"

# slug -> (German label, path d strings — one or more subpaths)
ICONS: dict[str, tuple[str, list[str]]] = {
    # Cars
    "car_sedan": ("Limousine", ["M4 10h16l-1 5H5l-1-5zm2 7a2 2 0 110-4 2 2 0 010 4zm12 0a2 2 0 110-4 2 2 0 010 4z"]),
    "car_suv": ("SUV", ["M3 11h18v4H3v-4zm2-2l2-3h10l2 3M6 18a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0zm12 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0z"]),
    "car_coupe": ("Coupé", ["M5 12h14l-2 4H7l-2-4zm1-3l3-4h8l3 4M7 17.5a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zm10 0a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z"]),
    "car_hatchback": ("Kleinwagen", ["M4 11h16v5H4v-5zm2-2l2-2h8l2 2M6 17a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zm12 0a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z"]),
    # Trucks
    "truck_pickup": ("Pickup", ["M3 12h10v5H3v-5zm10-3h5l3 3v5h-8V9zM6 18a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zm12 0a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z"]),
    "truck_semi": ("Sattelzug", ["M2 13h9v4H2v-4zm9-4h7l3 4v4h-10V9zM5 18.5a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zm14 0a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z"]),
    "truck_van": ("Transporter", ["M3 11h14v6H3v-6zm2-3h10l2 3M6 18a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zm10 0a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0z"]),
    # Racing
    "racing_flag": ("Zielflagge", ["M5 4v16M5 4h12l-2 3 2 3-2 3 2 3H5"]),
    "racing_helmet": ("Rennhelm", ["M12 4c-4 0-7 2-7 6v2h14v-2c0-4-3-6-7-6zm-9 8v3h18v-3H3zm3 3h12v2H6v-2z"]),
    "racing_wheel": ("Rennrad", ["M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0-1v2M12 19v2M4 12H2M22 12h-2M6 7l-1.5-1.5M18 17l1.5 1.5M18 7l1.5-1.5M6 17l-1.5 1.5"]),
    # Wolves
    "wolf_head": ("Wolf", ["M12 3L7 9v3l-2 4 7-2 7 2-2-4V9l-5-6zm-5 9l2 6 3-3 3 3 2-6-7 2-7-2z"]),
    # Lions
    "lion_head": ("Löwe", ["M12 4c-3 0-6 2-6 5 0 2 1 3 2 4l-1 5h10l-1-5c1-1 2-2 2-4 0-3-3-5-6-5zm-4 6c1 1 2 2 4 2s3-1 4-2"]),
    "lion_mane": ("Löwenmähne", ["M12 5a7 7 0 00-7 7c0 4 3 7 7 7s7-3 7-7a7 7 0 00-7-7zm0 3a4 4 0 014 4 4 4 0 01-8 0 4 4 0 014-4z"]),
    # Eagles
    "eagle_head": ("Adler", ["M12 5L8 11h2l-1 6 3-4 3 4-1-6h2l-4-6z"]),
    "eagle_wings": ("Adlerflügel", ["M12 6l-8 6 3 1 5-4 5 4 3-1-8-6zm0 4v10"]),
    # Crowns
    "crown_classic": ("Krone", ["M4 18h16v-2l-2-8-4 4-2-6-2 6-4-4-2 8v2z"]),
    "crown_royal": ("Königskrone", ["M5 17h14v-3L12 7 5 14v3zm2-10l2 3 3-5 3 5 2-3"]),
    # Stars
    "star_five": ("Stern", ["M12 2l2.9 6.9H22l-5.5 4.1 2.1 6.9L12 16.8 5.4 19.9l2.1-6.9L2 8.9h7.1z"]),
    "star_burst": ("Sternexplosion", ["M12 2v4M12 18v4M2 12h4M18 12h4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M19.1 4.9l-2.8 2.8M7.7 16.3l-2.8 2.8M12 8a4 4 0 100 8 4 4 0 000-8z"]),
    # Shields
    "shield_classic": ("Schild", ["M12 2L4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3z"]),
    "shield_heraldic": ("Wappenschild", ["M12 3l7 3v5c0 4-2.5 7.5-7 9-4.5-1.5-7-5-7-9V6l7-3zm0 5v8"]),
    # Tools
    "tool_wrench": ("Schraubenschlüssel", ["M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.1-2.1 2.5-2.5z"]),
    "tool_hammer": ("Hammer", ["M15 3l6 6-3 3-2-2-5 5 2 2-3 3-7-7 3-3 2 2 5-5-2-2 3-3z"]),
    "tool_gear": ("Zahnrad", ["M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-7 4l-2-1v-2l2-1 1-2h2l1 2 2 1v2l-2 1-1 2h-2l-1-2zM19 12l2-1v-2l-2-1-1-2h-2l-1 2-2 1v2l2 1 1 2h2l1-2z"]),
    "tool_screwdriver": ("Schraubendreher", ["M14 2l4 4-8 8-2 2-2-2 2-2 8-8zm-8 12l2 2-4 4-2-2 4-4z"]),
    # Technology
    "tech_chip": ("Chip", ["M8 8h8v8H8V8zm-4 4h2M14 8v-2h2M8 14H6v2M16 16v2h-2M8 6V4h2M16 10h2M10 16h2M10 6H8"]),
    "tech_wifi": ("WLAN", ["M12 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM5 10a11 11 0 0114 0M2 7a15 15 0 0120 0M8 13a7 7 0 018 0"]),
    "tech_code": ("Code", ["M8 7l-4 5 4 5M16 7l4 5-4 5M14 6l-4 12"]),
    "tech_antenna": ("Antenne", ["M12 20v-8M8 12a4 4 0 018 0M5 12a7 7 0 0114 0M2 12a10 10 0 0120 0"]),
    # Gaming
    "game_gamepad": ("Gamepad", ["M8 10H6v2H4v2h2v2h2v-2h2v-2h-2v-2zm10 0h-4a4 4 0 00-4 4v2a4 4 0 004 4h4a4 4 0 004-4v-2a4 4 0 00-4-4zm-2 4v2h2v-2h-2zm4 0v2h2v-2h-2z"]),
    "game_dice": ("Würfel", ["M6 6h12v12H6V6zm3 3h2v2H9V9zm6 0h2v2h-2V9zm-6 6h2v2H9v-2zm6 0h2v2h-2v-2z"]),
    "game_joystick": ("Joystick", ["M12 4v8M8 20h8M10 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM6 18h12v2H6v-2z"]),
    # Sports
    "sport_soccer": ("Fußball", ["M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 3l3 5H9l3-5zm-6 6h5l-3 5-2-5zm12 0l-2 5-3-5h5z"]),
    "sport_basketball": ("Basketball", ["M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM4 12h16M12 4c3 3 3 13 0 16M12 4c-3 3-3 13 0 16"]),
    "sport_tennis": ("Tennis", ["M8 4c4 0 8 4 8 8s-4 8-8 8S0 16 0 12s4-8 8-8zm4 8l6 8"]),
    "sport_trophy": ("Pokal", ["M8 4h8v4c0 3-2 5-4 6v2h4v-2c-2-1-4-3-4-6V4zM6 6H4c0 3 2 5 4 5M18 6h2c0 3-2 5-4 5M9 20h6v2H9v-2z"]),
    # Military-style (generic)
    "military_medal": ("Medaille", ["M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 16l-2 4 6-2 6 2-2-4"]),
    "military_chevron": ("Chevron", ["M6 16l6-8 6 8H6zM8 18h8l-4-6-4 6z"]),
    "military_badge": ("Abzeichen", ["M12 2l3 4h5l-1 5 3 3-5 1-1 5-4-2-4 2-1-5-5-1 3-3-1-5h5l3-4z"]),
    # Mechanical
    "mech_gear": ("Getriebe", ["M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM12 4v2M12 18v2M4 12h2M18 12h2M6 6l1.5 1.5M16.5 16.5L18 18M18 6l-1.5 1.5M7.5 16.5L6 18"]),
    "mech_bolt": ("Schraube", ["M10 2h4l1 6H9l1-6zM9 8h6v10a2 2 0 01-4 0V8z"]),
    "mech_chain": ("Kette", ["M6 10a2 2 0 104 0 2 2 0 00-4 0zm8 0a2 2 0 104 0 2 2 0 00-4 0zm-4 4a2 2 0 104 0 2 2 0 00-4 0zM6 14h12"]),
    "mech_piston": ("Kolben", ["M10 4h4v4h-4V4zM8 8h8v10H8V8zM6 20h12v2H6v-2z"]),
    # Abstract
    "abstract_diamond": ("Diamant", ["M12 3l9 7-9 11-9-11 9-7z"]),
    "abstract_hex": ("Sechseck", ["M12 2l8.5 5v10L12 22l-8.5-5V7L12 2z"]),
    "abstract_spiral": ("Spirale", ["M12 12a6 6 0 01-6-6 6 6 0 016 6 4 4 0 014 4 4 4 0 01-4-4 2 2 0 00-2-2 2 2 0 012 2"]),
    "abstract_wave": ("Welle", ["M3 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"]),
    # Premium decorative
    "decor_laurel": ("Lorbeer", ["M12 4c-4 2-6 5-6 8 0 3 2 5 6 6 4-1 6-3 6-6 0-3-2-6-6-8zM8 10l-3 2M16 10l3 2M7 14l-4 1M17 14l4 1"]),
    "decor_gem": ("Edelstein", ["M8 8h8l4 6-8 8-8-8 4-6zM12 8v16"]),
    "decor_ribbon": ("Schleife", ["M8 6c-2 0-3 2-3 4s1 4 3 4h2l2 4 2-4h2c2 0 3-2 3-4s-1-4-3-4H8z"]),
}


def write_svg(path: Path, fill: str, paths: list[str]) -> None:
    parts = "".join(f'<path d="{d}"/>' for d in paths)
    content = (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="{fill}">'
        f"{parts}</svg>\n"
    )
    path.write_text(content, encoding="utf-8")


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    slugs = sorted(ICONS.keys())
    if len(slugs) != 50:
        raise SystemExit(f"Expected 50 icons, got {len(slugs)}")
    for slug, (_label, paths) in ICONS.items():
        for color, hex_fill in (("white", "#ffffff"), ("black", "#000000")):
            write_svg(OUT / f"{slug}-{color}.svg", hex_fill, paths)
    manifest = ROOT / "includes" / "bundled-generic-icons.php"
    lines = ["<?php", "/** Auto-generated by tools/generate-generic-icons.py — do not edit by hand. */", "defined( 'ABSPATH' ) || exit;", "return array("]
    for slug in slugs:
        label, _ = ICONS[slug]
        safe_label = label.replace("'", "\\'")
        lines.append(f"\t'{slug}' => '{safe_label}',")
    lines.append(");")
    lines.append("")
    manifest.write_text("\n".join(lines), encoding="utf-8")
    print(f"OK generated {len(slugs)} icons ({len(slugs) * 2} SVG files) + manifest")


if __name__ == "__main__":
    main()
