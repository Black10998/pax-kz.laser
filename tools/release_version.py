#!/usr/bin/env python3
"""Shared release version validation and sync for protected ZIP builds."""

from __future__ import annotations

import hashlib
import json
import re
import sys
import zipfile
from dataclasses import dataclass
from pathlib import Path

SLUG = "pckz-canonical-engine"
MAIN_REL = f"{SLUG}/pckz-canonical-engine.php"
MANIFEST_REL = f"{SLUG}/RELEASE_MANIFEST.json"
ZIP_NAME_TEMPLATE = f"{SLUG}-{{version}}-protected.zip"

# Mirrors PCKZ_Licensing::plugin_header_version_from_content().
HEADER_VERSION_RE = re.compile(r"^[ \t/*#@]*Version:\s*(.+)$", re.MULTILINE | re.IGNORECASE)
PCKZCE_VERSION_RE = re.compile(
    r"define\s*\(\s*['\"]PCKZCE_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)"
)
PCKZCE_BUILD_RE = re.compile(
    r"define\s*\(\s*['\"]PCKZCE_BUILD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)"
)
HEADER_DOC_VERSION_RE = re.compile(
    r"^\s*\*\s*Version:\s*(.+)$", re.MULTILINE
)
README_STABLE_TAG_RE = re.compile(r"^Stable tag:\s*(.+)$", re.MULTILINE | re.IGNORECASE)
FILENAME_VERSION_RE = re.compile(
    rf"^{re.escape(SLUG)}-([0-9]+(?:\.[0-9]+)*)-protected\.zip$", re.IGNORECASE
)


@dataclass
class PluginVersionInfo:
    header_version: str
    pckzce_version: str
    pckzce_build: str
    readme_stable_tag: str

    def as_dict(self) -> dict[str, str]:
        return {
            "header_version": self.header_version,
            "pckzce_version": self.pckzce_version,
            "pckzce_build": self.pckzce_build,
            "readme_stable_tag": self.readme_stable_tag,
        }


def sanitize_text_field(value: str) -> str:
    """Approximate WordPress sanitize_text_field for version strings."""
    value = re.sub(r"<[^>]*>", "", value)
    value = value.replace("\r", " ").replace("\n", " ").replace("\t", " ")
    return value.strip()


def plugin_header_version_from_content(content: str) -> str:
    match = HEADER_VERSION_RE.search(content)
    if not match:
        return ""
    return sanitize_text_field(match.group(1))


def read_plugin_versions(root: Path) -> PluginVersionInfo:
    main_path = root / "pckz-canonical-engine.php"
    readme_path = root / "readme.txt"
    if not main_path.is_file():
        raise FileNotFoundError(f"Missing plugin main file: {main_path}")
    main = main_path.read_text(encoding="utf-8")
    readme = readme_path.read_text(encoding="utf-8") if readme_path.is_file() else ""
    header = plugin_header_version_from_content(main)
    pckzce = PCKZCE_VERSION_RE.search(main)
    build = PCKZCE_BUILD_RE.search(main)
    stable = README_STABLE_TAG_RE.search(readme)
    return PluginVersionInfo(
        header_version=header,
        pckzce_version=pckzce.group(1).strip() if pckzce else "",
        pckzce_build=build.group(1).strip() if build else "",
        readme_stable_tag=stable.group(1).strip() if stable else "",
    )


def validate_source_matches_release(root: Path, expected_version: str) -> PluginVersionInfo:
    info = read_plugin_versions(root)
    expected = sanitize_text_field(expected_version)
    errors: list[str] = []

    if not expected:
        errors.append("Expected release version is empty.")
    if info.header_version != expected:
        errors.append(
            f"Plugin header Version ({info.header_version!r}) != release version ({expected!r})"
        )
    if info.pckzce_version != expected:
        errors.append(
            f"PCKZCE_VERSION constant ({info.pckzce_version!r}) != release version ({expected!r})"
        )
    if info.readme_stable_tag and info.readme_stable_tag != expected:
        errors.append(
            f"readme.txt Stable tag ({info.readme_stable_tag!r}) != release version ({expected!r})"
        )
    if not info.pckzce_build.startswith(expected + "."):
        errors.append(
            f"PCKZCE_BUILD ({info.pckzce_build!r}) should start with {expected + '.'!r}"
        )
    if errors:
        raise ValueError("Pre-build version validation failed:\n  - " + "\n  - ".join(errors))
    return info


def sync_release_version(root: Path, version: str, build_id: str) -> PluginVersionInfo:
    version = sanitize_text_field(version)
    build_id = sanitize_text_field(build_id or version)
    main_path = root / "pckz-canonical-engine.php"
    readme_path = root / "readme.txt"
    main = main_path.read_text(encoding="utf-8")
    main = HEADER_DOC_VERSION_RE.sub(f" * Version:           {version}", main, count=1)
    main = PCKZCE_VERSION_RE.sub(
        f"define( 'PCKZCE_VERSION', '{version}' )", main, count=1
    )
    main = PCKZCE_BUILD_RE.sub(
        f"define( 'PCKZCE_BUILD', '{build_id}' )", main, count=1
    )
    main_path.write_text(main, encoding="utf-8")
    if readme_path.is_file():
        readme = readme_path.read_text(encoding="utf-8")
        readme = README_STABLE_TAG_RE.sub(f"Stable tag: {version}", readme, count=1)
        readme_path.write_text(readme, encoding="utf-8")
    return validate_source_matches_release(root, version)


def parse_release_version_from_filename(filename: str) -> str:
    match = FILENAME_VERSION_RE.match(Path(filename).name)
    return sanitize_text_field(match.group(1)) if match else ""


def validate_protected_zip(zip_path: Path, expected_version: str) -> dict:
    zip_path = zip_path.resolve()
    expected = sanitize_text_field(expected_version)
    filename_version = parse_release_version_from_filename(zip_path.name)
    if filename_version != expected:
        raise ValueError(
            f"ZIP filename version ({filename_version!r}) != expected release ({expected!r})"
        )
    if not zip_path.is_file():
        raise FileNotFoundError(f"Protected ZIP not found: {zip_path}")

    package_sha256 = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    with zipfile.ZipFile(zip_path) as zf:
        try:
            main_raw = zf.read(MAIN_REL).decode("utf-8", "replace")
        except KeyError as exc:
            raise ValueError(f"Missing {MAIN_REL} in archive") from exc

        header_version = plugin_header_version_from_content(main_raw)
        if header_version != expected:
            raise ValueError(
                "Protected archive plugin header version does not match release version: "
                f"header={header_version!r}, expected={expected!r}, filename={filename_version!r}"
            )

        pckzce = PCKZCE_VERSION_RE.search(main_raw)
        if not pckzce or sanitize_text_field(pckzce.group(1)) != expected:
            found = pckzce.group(1) if pckzce else ""
            raise ValueError(
                f"PCKZCE_VERSION inside ZIP ({found!r}) != release version ({expected!r})"
            )

        try:
            manifest = json.loads(zf.read(MANIFEST_REL).decode("utf-8"))
        except KeyError as exc:
            raise ValueError(f"Missing {MANIFEST_REL} in archive") from exc
        manifest_version = sanitize_text_field(str(manifest.get("version", "")))
        if manifest_version != expected:
            raise ValueError(
                f"RELEASE_MANIFEST.json version ({manifest_version!r}) != release version ({expected!r})"
            )

        files = manifest.get("files") or {}
        if not isinstance(files, dict):
            raise ValueError("RELEASE_MANIFEST.json files map is invalid")
        for rel, expected_hash in files.items():
            rel = str(rel).lstrip("/")
            candidates = [rel, f"{SLUG}/{rel}"]
            data = None
            for candidate in candidates:
                try:
                    data = zf.read(candidate)
                    break
                except KeyError:
                    continue
            if data is None:
                raise ValueError(f"Manifest file missing in archive: {rel}")
            actual_hash = hashlib.sha256(data).hexdigest()
            if sanitize_text_field(str(expected_hash)) != actual_hash:
                raise ValueError(f"Manifest hash mismatch for file: {rel}")

    return {
        "package_sha256": package_sha256,
        "header_version": header_version,
        "manifest_version": manifest_version,
        "manifest_file_count": len(files),
        "filename_version": filename_version,
    }


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    sys.exit(1)
