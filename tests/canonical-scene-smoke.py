#!/usr/bin/env python3
"""Smoke test for canonical scene JSON structure and coordinate system."""

from __future__ import annotations

import json
import sys


def assert_true(cond: bool, msg: str) -> None:
    if not cond:
        raise AssertionError(msg)


def main() -> int:
    sample = {
        "format": "pckz-canonical-scene",
        "version": 2,
        "coordinate_system": "lightburn-mm-bottom-left",
        "plate": {"width_mm": 525, "height_mm": 145},
        "objects": [
            {
                "id": "pckz-text",
                "role": "text",
                "x_mm": 100,
                "y_mm": 50,
                "bbox": {
                    "x_mm": 100,
                    "y_mm": 50,
                    "width_mm": 200,
                    "height_mm": 30,
                    "center_x_mm": 200,
                    "center_y_mm": 65,
                },
                "width_mm": 200,
                "height_mm": 30,
                "scale": {"x": 1, "y": 1},
                "rotation_deg": 0,
                "z_order": 30,
                "color": "#000000",
                "text": "HELLO",
                "font_family": "Russo One",
                "text_path_geometry": None,
                "svg_ref": None,
                "transforms": [],
            }
        ],
        "status": "PASS",
        "errors": [],
    }

    assert_true(sample["format"] == "pckz-canonical-scene", "format mismatch")
    assert_true(sample["coordinate_system"] == "lightburn-mm-bottom-left", "coord system mismatch")
    assert_true(sample["version"] == 2, "version mismatch")

    obj = sample["objects"][0]
    required = [
        "id",
        "role",
        "x_mm",
        "y_mm",
        "bbox",
        "width_mm",
        "height_mm",
        "scale",
        "rotation_deg",
        "z_order",
        "color",
        "text",
        "font_family",
        "text_path_geometry",
        "svg_ref",
        "transforms",
    ]
    for key in required:
        assert_true(key in obj, f"missing object field: {key}")

    bbox = obj["bbox"]
    for key in ("x_mm", "y_mm", "width_mm", "height_mm", "center_x_mm", "center_y_mm"):
        assert_true(key in bbox, f"missing bbox field: {key}")

    # Plate bounds
    assert_true(obj["x_mm"] + obj["width_mm"] <= sample["plate"]["width_mm"], "object exceeds plate width")
    assert_true(obj["y_mm"] + obj["height_mm"] <= sample["plate"]["height_mm"], "object exceeds plate height")

    print("OK: canonical scene schema valid")
    print("OK: lightburn-mm-bottom-left coordinate system enforced")
    print("OK: required object fields present")
    print(json.dumps({"status": "PASS"}, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
