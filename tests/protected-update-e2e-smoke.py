#!/usr/bin/env python3
"""
End-to-end smoke: Master publish -> client discovery -> update install path.

Simulates the protected update workflow using the same validation rules as
PCKZ_Licensing without requiring a live WordPress install.
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2.28.16"
ZIP_NAME = f"pckz-canonical-engine-{VERSION}-protected.zip"
ZIP_PATH = ROOT / "release-packages" / ZIP_NAME
INSTALLED_VERSION = "2.28.15"


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    sys.exit(1)


def plugin_header_version(content: str) -> str:
    match = re.search(r"^\s*\*\s*Version:\s*(.+)$", content, re.MULTILINE)
    return match.group(1).strip() if match else ""


def build_release_token(meta: dict) -> str:
    payload = json.dumps(
        {
            "version": meta.get("version", ""),
            "package_sha256": meta.get("package_sha256", ""),
            "package_url": meta.get("package_url", ""),
            "published_at": meta.get("published_at", ""),
        },
        separators=(",", ":"),
    )
    return hashlib.sha256(payload.encode()).hexdigest()[:32]


def validate_zip(path: Path, expected_version: str) -> dict:
    if not path.is_file():
        fail(f"Missing protected ZIP: {path}")
    package_sha256 = hashlib.sha256(path.read_bytes()).hexdigest()
    with zipfile.ZipFile(path) as zf:
        main = zf.read("pckz-canonical-engine/pckz-canonical-engine.php").decode("utf-8", "replace")
        header_version = plugin_header_version(main)
        if header_version != expected_version:
            fail(f"Plugin header version {header_version!r} != {expected_version!r}")
        manifest_raw = zf.read("pckz-canonical-engine/RELEASE_MANIFEST.json")
        manifest = json.loads(manifest_raw)
        if manifest.get("version") != expected_version:
            fail(f"Manifest version mismatch: {manifest.get('version')}")
        files = manifest.get("files") or {}
        for rel, expected in files.items():
            data = zf.read(f"pckz-canonical-engine/{rel}")
            actual = hashlib.sha256(data).hexdigest()
            if actual != expected:
                fail(f"Manifest hash mismatch for {rel}")
    return {
        "package_sha256": package_sha256,
        "manifest_version": manifest.get("version"),
        "manifest_file_count": len(files),
    }


def master_publish(meta: dict) -> dict:
    meta = dict(meta)
    meta["published_at"] = "2026-06-05 12:00:00"
    meta["release_token"] = build_release_token(meta)
    return meta


def client_check_in(meta: dict, installed: str) -> dict:
    latest = meta["version"]
    token = meta["release_token"]
    update_available = bool(latest and installed and _vcmp(latest, installed) > 0)
    return {
        "authorized": True,
        "latest_version": latest,
        "release_token": token,
        "update_available": update_available,
    }


def client_update_meta(meta: dict, installed: str, known_token: str = "") -> dict:
    latest = meta["version"]
    token = meta["release_token"]
    token_changed = bool(token and known_token and token != known_token)
    if installed and _vcmp(latest, installed) <= 0 and not token_changed:
        return {
            "ok": True,
            "update_available": False,
            "version": latest,
            "release_token": token,
        }
    return {
        "ok": True,
        "update_available": True,
        "version": latest,
        "package": meta["package_url"],
        "package_sha256": meta["package_sha256"],
        "release_token": token,
    }


def resolve_client_update_meta(state: dict, cache: dict, installed: str) -> dict:
    master_latest = state.get("master_latest_version", "")
    master_token = state.get("master_release_token", "")
    cached_ver = cache.get("version") or cache.get("latest_version") or ""
    cached_token = cache.get("release_token") or ""

    if cached_ver and master_latest and _vcmp(cached_ver, master_latest) > 0:
        cache = {}
        cached_ver = cached_token = ""
    if master_token and cached_token and cached_token != master_token:
        cache = {}
        cached_ver = cached_token = ""
    if master_latest and _vcmp(master_latest, installed) <= 0:
        if cache.get("update_available") or (cached_ver and _vcmp(cached_ver, installed) > 0):
            cache = {}

    effective = master_latest or cached_ver
    update_available = bool(effective and _vcmp(effective, installed) > 0)
    if cache.get("ok"):
        return {
            **cache,
            "version": effective or cached_ver,
            "latest_version": effective or cached_ver,
            "update_available": update_available,
        }
    return {
        "ok": True,
        "version": master_latest,
        "latest_version": master_latest,
        "update_available": update_available,
        "from_state": True,
    }


def _vcmp(a: str, b: str) -> int:
    def parts(v: str) -> list[int]:
        return [int(x) for x in re.findall(r"\d+", v)]

    pa, pb = parts(a), parts(b)
    length = max(len(pa), len(pb))
    pa += [0] * (length - len(pa))
    pb += [0] * (length - len(pb))
    for x, y in zip(pa, pb):
        if x != y:
            return 1 if x > y else -1
    return 0


def simulate_install(package_path: Path, target_dir: Path) -> str:
    target_dir.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(package_path) as zf:
        prefix = "pckz-canonical-engine/"
        for name in zf.namelist():
            if not name.startswith(prefix) or name.endswith("/"):
                continue
            rel = name[len(prefix) :]
            dest = target_dir / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_bytes(zf.read(name))
    main = (target_dir / "pckz-canonical-engine.php").read_text(encoding="utf-8")
    return plugin_header_version(main)


def main() -> int:
    validated = validate_zip(ZIP_PATH, VERSION)
    package_url = (
        f"https://github.com/Black10998/pax-kz.laser/releases/download/v{VERSION}/{ZIP_NAME}"
    )
    published = master_publish(
        {
            "version": VERSION,
            "package_url": package_url,
            "package_sha256": validated["package_sha256"],
        }
    )

    check_in = client_check_in(published, INSTALLED_VERSION)
    if not check_in["update_available"]:
        fail("Client check-in should report update_available for 2.28.15 -> 2.28.16")

    update_meta = client_update_meta(published, INSTALLED_VERSION)
    if not update_meta.get("update_available") or not update_meta.get("package"):
        fail("Client update-meta should return package URL for pending update")

    state = {
        "master_latest_version": check_in["latest_version"],
        "master_release_token": check_in["release_token"],
    }
    stale_cache = {
        "ok": True,
        "version": "2.28.99",
        "latest_version": "2.28.99",
        "update_available": True,
        "release_token": "stale-token",
    }
    resolved = resolve_client_update_meta(state, stale_cache, INSTALLED_VERSION)
    if resolved.get("version") != VERSION:
        fail(f"Stale cache must not override master publish version (got {resolved.get('version')})")
    if not resolved.get("update_available"):
        fail("Resolved client meta should show update available")

    install_dir = Path("/tmp/pckz-e2e-install")
    if install_dir.exists():
        import shutil

        shutil.rmtree(install_dir)
    installed_version = simulate_install(ZIP_PATH, install_dir)
    if installed_version != VERSION:
        fail(f"Simulated install version {installed_version!r} != {VERSION!r}")

    print("OK protected-update-e2e-smoke")
    print(f"  ZIP validated: {ZIP_PATH.name}")
    print(f"  SHA256: {validated['package_sha256']}")
    print(f"  Master publish version: {published['version']}")
    print(f"  Release token: {published['release_token']}")
    print(f"  Client update available: {check_in['update_available']}")
    print(f"  Simulated install version: {installed_version}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
