#!/usr/bin/env python3
"""Smoke: release version guard rejects mismatched source before packaging."""

from __future__ import annotations

import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))
from release_version import validate_source_matches_release, validate_protected_zip  # noqa: E402


def main() -> int:
    try:
        validate_source_matches_release(ROOT, "9.99.99")
    except ValueError:
        pass
    else:
        print("FAIL: pre-build validation should reject mismatched release version", file=sys.stderr)
        return 1

    # Built artifact must pass post-build validation when version matches.
    version = "2.28.23"
    zip_path = ROOT / "release-packages" / f"pckz-canonical-engine-{version}-protected.zip"
    if not zip_path.is_file():
        print(f"FAIL: missing built ZIP at {zip_path}", file=sys.stderr)
        return 1
    validate_protected_zip(zip_path, version)
    print("OK release-version-guard-smoke")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
