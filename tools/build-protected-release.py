#!/usr/bin/env python3
"""Build protected customer ZIP package with strict version validation."""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

sys.path.insert(0, str(ROOT / "tools"))
from release_config import (  # noqa: E402
    load_release_config,
    should_skip_release_path,
    strip_client_asset_sources,
)
from release_version import (  # noqa: E402
    validate_protected_zip,
    validate_source_matches_release,
    sync_release_version,
)

CONFIG = load_release_config()
SLUG = str(CONFIG.get("slug") or "pckz-canonical-engine")


def should_skip(rel: Path) -> bool:
    return should_skip_release_path(rel)


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def build(version: str, build_id: str, output_dir: Path, channel: str) -> Path:
    print(f"Pre-build validation for {version}...")
    validate_source_matches_release(ROOT, version)

    stage_root = Path(tempfile.mkdtemp(prefix="pckzce-release-"))
    stage_dir = stage_root / SLUG
    stage_dir.mkdir(parents=True, exist_ok=True)

    try:
        for src in ROOT.rglob("*"):
            if not src.is_file():
                continue
            rel = src.relative_to(ROOT)
            if should_skip(rel):
                continue
            dest = stage_dir / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dest)

        strip_client_asset_sources(stage_dir)

        manifest_files: dict[str, str] = {}
        for file_path in sorted(stage_dir.rglob("*")):
            if not file_path.is_file():
                continue
            rel = file_path.relative_to(stage_dir).as_posix()
            if rel == "RELEASE_MANIFEST.json":
                continue
            manifest_files[rel] = sha256_file(file_path)

        manifest = {
            "slug": SLUG,
            "version": version,
            "build": build_id,
            "channel": channel,
            "created_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00"),
            "files": manifest_files,
            "signature": "",
            "signature_alg": "none",
            "signature_hint": "unsigned",
        }
        manifest_path = stage_dir / "RELEASE_MANIFEST.json"
        manifest_path.write_text(
            json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )

        output_dir.mkdir(parents=True, exist_ok=True)
        zip_path = (output_dir / f"{SLUG}-{version}-protected.zip").resolve()
        if zip_path.exists():
            zip_path.unlink()

        subprocess.run(
            ["zip", "-qr", str(zip_path), SLUG],
            cwd=stage_root,
            check=True,
        )

        print(f"Post-build validation for {zip_path.name}...")
        result = validate_protected_zip(zip_path, version)
        print("Post-build validation passed.")
        print(f"  header_version: {result['header_version']}")
        print(f"  manifest_version: {result['manifest_version']}")
        print(f"  manifest_file_count: {result['manifest_file_count']}")
        return zip_path
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)


def main() -> int:
    parser = argparse.ArgumentParser(description="Build a validated protected release ZIP.")
    parser.add_argument("--version", required=True, help="Release semver, must match plugin source.")
    parser.add_argument("--build", default="", help="Build identifier written to PCKZCE_BUILD/manifest.")
    parser.add_argument("--output", default=str(ROOT / "release-packages"))
    parser.add_argument(
        "--channel",
        default=str(CONFIG.get("channel") or "customer-protected"),
    )
    parser.add_argument(
        "--js-protection",
        action="store_true",
        help="Run tools/build-js-protection.php on the source tree before staging.",
    )
    parser.add_argument(
        "--sync-version",
        action="store_true",
        help="Write --version/--build into pckz-canonical-engine.php and readme.txt before building.",
    )
    args = parser.parse_args()
    version = args.version.strip()
    build_id = (args.build or version).strip()

    if args.sync_version:
        print(f"Syncing source files to version {version} (build {build_id})...")
        sync_release_version(ROOT, version, build_id)
    else:
        validate_source_matches_release(ROOT, version)

    if args.js_protection:
        js_script = ROOT / "tools" / "build-js-protection.php"
        if not js_script.is_file():
            raise FileNotFoundError(f"Missing JS protection script: {js_script}")
        print("Running JS/CSS protection pass...")
        subprocess.run(
            ["php", str(js_script), f"--root={ROOT}"],
            cwd=ROOT,
            check=True,
        )

    zip_path = build(version, build_id, Path(args.output), args.channel)
    digest = sha256_file(zip_path)
    print(f"Built protected release: {zip_path}")
    print(f"SHA256: {digest}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, FileNotFoundError) as exc:
        print(f"BUILD FAILED: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
