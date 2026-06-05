#!/usr/bin/env python3
"""Load shared release pipeline configuration."""

from __future__ import annotations

import json
from functools import lru_cache
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONFIG_PATH = ROOT / "tools" / "release-config.json"


@lru_cache(maxsize=1)
def load_release_config() -> dict:
    if not CONFIG_PATH.is_file():
        raise FileNotFoundError(f"Missing release config: {CONFIG_PATH}")
    data = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise ValueError("release-config.json must contain a JSON object")
    return data


def exclude_directories() -> set[str]:
    return set(load_release_config().get("exclude_directories") or [])


def client_exclude_paths() -> set[str]:
    return set(load_release_config().get("client_exclude_paths") or [])


def forbidden_archive_prefixes() -> list[str]:
    return list(load_release_config().get("forbidden_archive_paths") or [])


def should_skip_release_path(rel: Path) -> bool:
    parts = rel.parts
    if any(part in exclude_directories() for part in parts):
        return True
    normalized = rel.as_posix()
    for path in client_exclude_paths():
        if normalized == path or normalized.startswith(path + "/"):
            return True
    return False


def strip_client_asset_sources(stage_dir: Path) -> None:
    config = load_release_config()
    if config.get("js_strip_source_when_min_exists", True):
        js_root = stage_dir / "public" / "js"
        if js_root.is_dir():
            for js_file in js_root.rglob("*.js"):
                name = js_file.name
                if name.endswith(".min.js") or name.endswith(".protected.js") or name == "index.php":
                    continue
                min_path = js_file.with_name(js_file.stem + ".min.js")
                if min_path.is_file():
                    js_file.unlink(missing_ok=True)
    if config.get("css_strip_source_when_min_exists", True):
        css_root = stage_dir / "public" / "css"
        if css_root.is_dir():
            for css_file in css_root.rglob("*.css"):
                if css_file.name.endswith(".min.css") or css_file.name == "index.php":
                    continue
                min_path = css_file.with_name(css_file.stem + ".min.css")
                if min_path.is_file():
                    css_file.unlink(missing_ok=True)
    if config.get("strip_source_maps", True):
        for asset_root in (stage_dir / "public" / "js", stage_dir / "public" / "css"):
            if not asset_root.is_dir():
                continue
            for map_file in asset_root.rglob("*.map"):
                map_file.unlink(missing_ok=True)
