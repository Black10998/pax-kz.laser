#!/usr/bin/env python3
"""
Import 10 customer red line models from LightBurn .lbrn2 (native vector paths).
Output: public/assets/lines/type_102.svg … type_111.svg (950×35, matte red #B22222).
"""

from __future__ import annotations

import argparse
import importlib.util
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

MATTE_RED = "#B22222"
START_TYPE = 102
MODEL_COUNT = 10


def load_converter():
	root = Path(__file__).resolve().parent
	spec = importlib.util.spec_from_file_location(
		"pckz_convert_ai", root / "convert-lightburn-ai-to-svg.py"
	)
	mod = importlib.util.module_from_spec(spec)
	assert spec.loader
	spec.loader.exec_module(mod)
	return mod


def decode_vert_list(text: str) -> list[tuple[float, float]]:
	verts: list[tuple[float, float]] = []
	for m in re.finditer(
		r"V\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s+([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)",
		text,
	):
		verts.append((float(m.group(1)), float(m.group(2))))
	return verts


def decode_prim_list(text: str) -> list[tuple[int, int]]:
	prims: list[tuple[int, int]] = []
	for m in re.finditer(r"L(\d+)\s+(\d+)", text):
		prims.append((int(m.group(1)), int(m.group(2))))
	return prims


def shape_to_path_dict(shape_el: ET.Element) -> dict | None:
	shape_type = (shape_el.get("Type") or "").lower()
	if shape_type not in ("path", ""):
		return None
	vert_el = shape_el.find("VertList")
	if vert_el is None or not (vert_el.text or "").strip():
		# Legacy <V vx= vy=>
		verts = []
		for v in shape_el.findall("V"):
			try:
				verts.append((float(v.get("vx", 0)), float(v.get("vy", 0))))
			except ValueError:
				continue
		if len(verts) < 2:
			return None
		ops = [("M", verts[0][0], verts[0][1])]
		for i in range(1, len(verts)):
			ops.append(("L", verts[i][0], verts[i][1]))
		closed = (shape_el.get("IsClosed") or "0") == "1"
		return {"ops": ops, "stroked": False, "closed": closed}

	verts = decode_vert_list(vert_el.text or "")
	if len(verts) < 2:
		return None
	prims = decode_prim_list((shape_el.findtext("PrimList") or ""))
	ops: list[tuple[str, float, float]] = [("M", verts[0][0], verts[0][1])]
	if prims:
		for _p0, p1 in prims:
			if 0 <= p1 < len(verts):
				ops.append(("L", verts[p1][0], verts[p1][1]))
	else:
		for x, y in verts[1:]:
			ops.append(("L", x, y))
	closed = (shape_el.get("IsClosed") or "0") == "1"
	if closed and len(verts) > 2:
		ops.append(("L", verts[0][0], verts[0][1]))
	return {"ops": ops, "stroked": False, "closed": closed}


def path_centroid(path: dict) -> tuple[float, float]:
	xs = [op[1] for op in path["ops"]]
	ys = [op[2] for op in path["ops"]]
	return (sum(xs) / len(xs), sum(ys) / len(ys))


def extract_path_shapes(lbrn2_path: Path) -> list[dict]:
	root = ET.fromstring(lbrn2_path.read_text(encoding="utf-8", errors="replace"))
	paths: list[dict] = []
	for shape in root.iter("Shape"):
		pd = shape_to_path_dict(shape)
		if pd:
			paths.append(pd)
	return paths


def cluster_paths(paths: list[dict], count: int) -> list[list[dict]]:
	if not paths:
		return [[] for _ in range(count)]
	# Sort by vertical position (LightBurn Y-up: higher Y = upper row on sheet).
	sorted_paths = sorted(paths, key=lambda p: path_centroid(p)[1], reverse=True)
	clusters: list[list[dict]] = [[] for _ in range(count)]
	n = len(sorted_paths)
	for i, path in enumerate(sorted_paths):
		bucket = min(count - 1, (i * count) // n)
		clusters[bucket].append(path)
	return clusters


def main() -> int:
	parser = argparse.ArgumentParser(description="Import LightBurn .lbrn2 line sheet")
	parser.add_argument("source", type=Path, help="Source .lbrn2 file")
	parser.add_argument(
		"-o",
		"--output",
		type=Path,
		default=Path(__file__).resolve().parent.parent / "public/assets/lines",
	)
	parser.add_argument("--start-type", type=int, default=START_TYPE)
	parser.add_argument("--count", type=int, default=MODEL_COUNT)
	parser.add_argument("--fill-color", default=MATTE_RED)
	args = parser.parse_args()

	if not args.source.is_file():
		print(f"Missing source: {args.source}", file=sys.stderr)
		return 1

	conv = load_converter()
	paths = extract_path_shapes(args.source)
	if not paths:
		print(f"No Path shapes in {args.source.name}", file=sys.stderr)
		return 1

	clusters = cluster_paths(paths, args.count)
	non_empty = sum(1 for c in clusters if c)
	if non_empty < args.count:
		print(
			f"WARN: only {non_empty} non-empty clusters from {len(paths)} paths",
			file=sys.stderr,
		)

	args.output.mkdir(parents=True, exist_ok=True)
	for i, cluster in enumerate(clusters):
		num = args.start_type + i
		dest = args.output / f"type_{num}.svg"
		if not cluster:
			print(f"SKIP empty cluster -> type_{num}.svg", file=sys.stderr)
			continue
		conv.convert_paths(cluster, dest, args.source.name + f"#{num}", fill_color=args.fill_color)
		print(f"OK type_{num}.svg ({len(cluster)} paths)")

	print(
		f"Imported {args.count} models (type_{args.start_type}–type_{args.start_type + args.count - 1})"
	)
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
