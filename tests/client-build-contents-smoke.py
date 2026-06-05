#!/usr/bin/env python3
"""Smoke: client protected build excludes master-only artifacts."""

from __future__ import annotations

import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

from release_config import forbidden_archive_prefixes, load_release_config  # noqa: E402
from release_version import read_plugin_versions  # noqa: E402

FORBIDDEN = forbidden_archive_prefixes()


def main() -> int:
    load_release_config()
    version = read_plugin_versions(ROOT).header_version
    zip_path = ROOT / "release-packages" / f"pckz-canonical-engine-{version}-protected.zip"
    if not zip_path.is_file():
        print(f"FAIL: missing ZIP at {zip_path}", file=sys.stderr)
        return 1

    offenders: list[str] = []
    with zipfile.ZipFile(zip_path) as zf:
        for name in zf.namelist():
            normalized = name.replace("\\", "/")
            if not normalized.startswith("pckz-canonical-engine/"):
                continue
            inside = normalized[len("pckz-canonical-engine/") :]
            for bad in FORBIDDEN:
                if bad and bad in inside:
                    offenders.append(normalized)
                    break

    if offenders:
        print("FAIL: client ZIP contains master-only paths:", file=sys.stderr)
        for item in offenders[:20]:
            print(f"  - {item}", file=sys.stderr)
        return 1

    print("OK client-build-contents-smoke")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
