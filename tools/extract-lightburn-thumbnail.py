#!/usr/bin/env python3
"""
Extract embedded LightBurn thumbnail PNG from .lbt/.lbrn2 files.
"""

from __future__ import annotations

import argparse
import base64
import sys
import xml.etree.ElementTree as ET
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract LightBurn thumbnail image.")
    parser.add_argument("source", type=Path, help="LightBurn .lbt/.lbrn2 source file")
    parser.add_argument("output", type=Path, help="Destination PNG path")
    parser.add_argument("--force", action="store_true", help="Overwrite output if present")
    args = parser.parse_args()

    if not args.source.is_file():
        print(f"Missing source: {args.source}", file=sys.stderr)
        return 1
    if args.output.exists() and not args.force:
        print(f"Output exists (use --force): {args.output}", file=sys.stderr)
        return 1

    try:
        root = ET.fromstring(args.source.read_text(encoding="utf-8", errors="replace"))
    except ET.ParseError as exc:
        print(f"Invalid XML in {args.source}: {exc}", file=sys.stderr)
        return 1

    thumb = root.find("Thumbnail")
    if thumb is None:
        print("Missing <Thumbnail> element.", file=sys.stderr)
        return 1

    encoded = thumb.get("Source") or ""
    if not encoded.strip():
        print("Missing thumbnail Source attribute.", file=sys.stderr)
        return 1

    try:
        payload = base64.b64decode(encoded)
    except (ValueError, base64.binascii.Error) as exc:
        print(f"Invalid base64 thumbnail data: {exc}", file=sys.stderr)
        return 1

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_bytes(payload)
    print(f"Wrote thumbnail to {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
