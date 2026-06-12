#!/usr/bin/env python3
"""End-to-end protected client release pipeline."""

from __future__ import annotations

import argparse
import hashlib
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

from release_config import load_release_config  # noqa: E402
from release_version import read_plugin_versions, sync_release_version, validate_source_matches_release  # noqa: E402


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def update_sha256sums(zip_path: Path, digest: str) -> None:
    sums_path = ROOT / "release-packages" / "SHA256SUMS.txt"
    lines: list[str] = []
    if sums_path.is_file():
        lines = [
            line
            for line in sums_path.read_text(encoding="utf-8").splitlines()
            if line.strip() and not line.endswith(f"  {zip_path.name}")
        ]
    lines.append(f"{digest}  {zip_path.name}")
    sums_path.parent.mkdir(parents=True, exist_ok=True)
    sums_path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser(description="Run the full protected client release pipeline.")
    parser.add_argument("--version", default="", help="Release version (defaults to plugin source).")
    parser.add_argument("--build", default="", help="Build identifier (auto-generated when blank).")
    parser.add_argument("--output", default=str(ROOT / "release-packages"))
    parser.add_argument("--sync-version", action="store_true", help="Write version/build into source files.")
    parser.add_argument("--js-protection", action="store_true", help="Run JS/CSS protection before packaging.")
    args = parser.parse_args()

    load_release_config()
    source = read_plugin_versions(ROOT)
    version = (args.version or source.header_version).strip()
    if not version:
        raise ValueError("Could not determine release version from source or --version.")

    build_id = (args.build or "").strip()
    if not build_id:
        stamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
        build_id = f"{version}.{stamp}-client-protected"

    if args.sync_version:
        print(f"Syncing source to version {version} (build {build_id})...")
        sync_release_version(ROOT, version, build_id)
    else:
        validate_source_matches_release(ROOT, version)

    builder = ROOT / "tools" / "build-protected-release.py"
    cmd = [
        sys.executable,
        str(builder),
        f"--version={version}",
        f"--build={build_id}",
        f"--output={args.output}",
    ]
    if args.js_protection:
        cmd.append("--js-protection")
    print("Building protected client package...")
    subprocess.run(cmd, cwd=ROOT, check=True)

    zip_path = Path(args.output) / f"pckz-canonical-engine-{version}-protected.zip"
    digest = sha256_file(zip_path)
    update_sha256sums(zip_path, digest)
    print(f"Release pipeline complete: {zip_path}")
    print(f"SHA256: {digest}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, FileNotFoundError, subprocess.CalledProcessError) as exc:
        print(f"RELEASE PIPELINE FAILED: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
