#!/usr/bin/env python3
"""Build full Master Build ZIP for paxdesign.at (includes all Master Control UI)."""

from __future__ import annotations

import argparse
import hashlib
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

from release_config import load_release_config  # noqa: E402
from release_version import read_plugin_versions  # noqa: E402

CONFIG = load_release_config()
SLUG = str(CONFIG.get("slug") or "pckz-canonical-engine")

# Master installs keep release tooling on-server; never ship dev-only trees.
MASTER_EXTRA_EXCLUDES = {
    "release-packages",
    "tests",
    "import",
    ".git",
    ".github",
    ".cursor",
    "node_modules",
    "dist",
    "tmp",
    ".DS_Store",
    ".env",
}


def should_skip_master_path(rel: Path) -> bool:
    return any(part in MASTER_EXTRA_EXCLUDES for part in rel.parts)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build(version: str, output_dir: Path) -> Path:
    stage_root = Path(tempfile.mkdtemp(prefix="pckzce-master-"))
    stage_dir = stage_root / SLUG
    stage_dir.mkdir(parents=True, exist_ok=True)

    try:
        for src in ROOT.rglob("*"):
            if not src.is_file():
                continue
            rel = src.relative_to(ROOT)
            if should_skip_master_path(rel):
                continue
            dest = stage_dir / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dest)

        output_dir.mkdir(parents=True, exist_ok=True)
        zip_name = f"{SLUG}-{version}-master.zip"
        zip_path = (output_dir / zip_name).resolve()
        if zip_path.exists():
            zip_path.unlink()

        subprocess.run(
            ["zip", "-qr", str(zip_path), SLUG],
            cwd=stage_root,
            check=True,
        )
        return zip_path
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)


def verify_master_zip(zip_path: Path) -> None:
    import zipfile

    required = [
        f"{SLUG}/pckz-canonical-engine.php",
        f"{SLUG}/includes/class-pckz-master-control.php",
        f"{SLUG}/admin/views/partials/licensing-master-releases.php",
        f"{SLUG}/admin/views/partials/licensing-master-nav.php",
        f"{SLUG}/tools/release-config.json",
    ]
    with zipfile.ZipFile(zip_path) as zf:
        names = set(zf.namelist())
        missing = [path for path in required if path not in names]
        if missing:
            raise ValueError("Master ZIP missing required paths: " + ", ".join(missing))


def main() -> int:
    parser = argparse.ArgumentParser(description="Build Master Build ZIP for paxdesign.at.")
    parser.add_argument("--version", default="", help="Version label for filename (defaults to plugin source).")
    parser.add_argument("--output", default=str(ROOT / "release-packages"))
    args = parser.parse_args()

    load_release_config()
    version = (args.version or read_plugin_versions(ROOT).header_version).strip()
    if not version:
        raise ValueError("Could not determine release version.")

    zip_path = build(version, Path(args.output))
    verify_master_zip(zip_path)
    digest = sha256_file(zip_path)

    print(f"Built master release: {zip_path}")
    print(f"SHA256: {digest}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, FileNotFoundError, subprocess.CalledProcessError) as exc:
        print(f"MASTER BUILD FAILED: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
