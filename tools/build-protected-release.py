#!/usr/bin/env python3
"""Build protected customer ZIP package with RELEASE_MANIFEST.json."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLUG = "pckz-canonical-engine"
EXCLUDES = {
    ".git",
    ".github",
    ".cursor",
    "dist",
    "release-packages",
    "tmp",
    "node_modules",
    "tests",
    "import",
    "tools",
    ".DS_Store",
    ".env",
}


def should_skip(rel: Path) -> bool:
    return any(part in EXCLUDES for part in rel.parts)


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def build(version: str, build_id: str, output_dir: Path, channel: str) -> Path:
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
        return zip_path
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--build", default="")
    parser.add_argument("--output", default=str(ROOT / "release-packages"))
    parser.add_argument("--channel", default="customer-protected")
    args = parser.parse_args()
    build_id = args.build or args.version
    zip_path = build(args.version, build_id, Path(args.output), args.channel)
    digest = sha256_file(zip_path)
    print(f"Built protected release: {zip_path}")
    print(f"SHA256: {digest}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
