#!/usr/bin/env python3
"""Verify frontend bootstrap contract for PCKZ Canonical Engine."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CREATOR = ROOT / "public/js/creator.js"
BOOT = ROOT / "public/js/bootstrap.js"
ASSETS = ROOT / "includes/class-pckz-assets.php"


def main() -> int:
    boot = BOOT.read_text(encoding="utf-8")
    creator = CREATOR.read_text(encoding="utf-8")
    assets = ASSETS.read_text(encoding="utf-8")

    assert "PCKZCE_GLOBAL" in boot, "bootstrap.js must define PCKZCE_GLOBAL"
    assert "global.PCKZCE_GLOBAL = global.PCKZCE_GLOBAL || global" in boot

    assert "(function (PCKZCE_GLOBAL)" in creator, "creator.js must accept PCKZCE_GLOBAL param"
    assert "PCKZ_GLOBAL" not in creator, "creator.js must not reference undefined PCKZ_GLOBAL"
    assert re.search(r"\bglobal\.", creator) is None, "creator.js must not use bare global"

    assert "'pckzce-bootstrap'" in assets, "assets must enqueue pckzce-bootstrap"
    assert "'pckzce-bootstrap', 'pckzce-fabric'" in assets.replace("\n", " "), "bootstrap must precede creator deps"
    assert "wp_localize_script(\n\t\t\t'pckzce-creator',\n\t\t\t'pckzceConfig'" in assets

    print("OK: bootstrap.js defines window.PCKZCE_GLOBAL")
    print("OK: creator.js uses PCKZCE_GLOBAL parameter (no bare global)")
    print("OK: assets enqueue bootstrap before creator dependency chain")
    print("OK: wp_localize_script targets pckzce-creator handle")
    return 0


if __name__ == "__main__":
    sys.exit(main())
