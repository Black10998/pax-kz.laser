#!/usr/bin/env python3
"""
Production export smoke tests (path mapping, ellipses, subpaths).
Run: python3 pckz-canonical-engine/tests/export-smoke.py
"""
import math
import re
import urllib.request

CANVAS_W = 525.0
CANVAS_H = 145.0
LINE_BOX = {"x": 88.0, "y": 40.0, "width": 348.0, "height": 65.0}
ICON_BOX = {"x": 18.0, "y": 55.0, "width": 24.0, "height": 24.0}


def fetch(url):
    return urllib.request.urlopen(url, timeout=25).read().decode("utf-8", "replace")


def parse_nums(s):
    return [float(n) for n in re.findall(r"-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?", s)]


def split_subpaths(d):
    chunks = re.split(r"(?=[Mm])", d)
    return [c.strip() for c in chunks if c.strip()]


def parse_path_raw(d):
    verts = []
    cx = cy = 0.0
    sx = sy = 0.0
    lx = ly = 0.0
    closed = False

    def line_to(nx, ny):
        nonlocal cx, cy
        if not verts:
            verts.append((nx, ny))
        else:
            verts.append((nx, ny))
        cx, cy = nx, ny

    def sample_cubic(x0, y0, x1, y1, x2, y2, x3, y3):
        for s in range(1, 13):
            t = s / 13
            mt = 1 - t
            nx = mt**3 * x0 + 3 * mt**2 * t * x1 + 3 * mt * t**2 * x2 + t**3 * x3
            ny = mt**3 * y0 + 3 * mt**2 * t * y1 + 3 * mt * t**2 * y2 + t**3 * y3
            line_to(nx, ny)

    for letter, nums_s in re.findall(r"([MmLlHhVvCcSsQqTtAaZz])([^MmLlHhVvCcSsQqTtAaZz]*)", d):
        nums = parse_nums(nums_s)
        rel = letter.islower()
        t = letter.upper()
        if t == "M":
            for n in range(0, len(nums) - 1, 2):
                nx = cx + nums[n] if rel else nums[n]
                ny = cy + nums[n + 1] if rel else nums[n + 1]
                if n == 0 and not verts:
                    verts.append((nx, ny))
                else:
                    line_to(nx, ny)
                cx, cy = nx, ny
                sx, sy = cx, cy
        elif t == "L":
            for n in range(0, len(nums) - 1, 2):
                nx = cx + nums[n] if rel else nums[n]
                ny = cy + nums[n + 1] if rel else nums[n + 1]
                line_to(nx, ny)
        elif t == "C":
            for n in range(0, len(nums) - 5, 6):
                x1 = cx + nums[n] if rel else nums[n]
                y1 = cy + nums[n + 1] if rel else nums[n + 1]
                x2 = cx + nums[n + 2] if rel else nums[n + 2]
                y2 = cy + nums[n + 3] if rel else nums[n + 3]
                x3 = cx + nums[n + 4] if rel else nums[n + 4]
                y3 = cy + nums[n + 5] if rel else nums[n + 5]
                sample_cubic(cx, cy, x1, y1, x2, y2, x3, y3)
                lx, ly = x2, y2
                cx, cy = x3, y3
        elif t == "Z" and len(verts) > 1:
            cx, cy = sx, sy
            closed = True
    return verts, closed


def map_to_box(verts, vb_w, vb_h, box, uniform=True):
    out = []
    if uniform:
        scale = min(box["width"] / vb_w, box["height"] / vb_h)
        cw = vb_w * scale
        ch = vb_h * scale
        ox = box["x"] + (box["width"] - cw) / 2
        oy = box["y"] + (box["height"] - ch) / 2
        for x, y in verts:
            out.append((ox + (x / vb_w) * cw, oy + (1 - (y / vb_h)) * ch))
    else:
        for x, y in verts:
            out.append(
                (
                    box["x"] + (x / vb_w) * box["width"],
                    box["y"] + (1 - (y / vb_h)) * box["height"],
                )
            )
    return out


def in_canvas(pts):
    for x, y in pts:
        if x < -5 or x > CANVAS_W + 5 or y < -5 or y > CANVAS_H + 5:
            return False
    return True


def parse_matrix(transform):
    m = re.search(r"matrix\s*\(\s*([^)]+)\)", transform or "", re.I)
    if not m:
        return [1, 0, 0, 1, 0, 0]
    parts = [float(p) for p in re.split(r"[\s,]+", m.group(1).strip()) if p]
    return parts[:6] if len(parts) >= 6 else [1, 0, 0, 1, 0, 0]


def apply_matrix(mat, x, y):
    a, b, c, d, e, f = mat
    return a * x + c * y + e, b * x + d * y + f


def ellipse_paths(body, vb_w, vb_h):
    paths = []
    for attrs in re.findall(r"<ellipse\b([^>]*)/?>", body, re.I):
        cx = cy = 0.0
        rx = ry = 1.0
        for pat, val in [(r'\bcx="([\d.]+)', "cx"), (r'\bcy="([\d.]+)', "cy"), (r'\brx="([\d.]+)', "rx"), (r'\bry="([\d.]+)', "ry")]:
            m = re.search(pat, attrs, re.I)
            if m:
                if val == "cx":
                    cx = float(m.group(1))
                elif val == "cy":
                    cy = float(m.group(1))
                elif val == "rx":
                    rx = float(m.group(1))
                elif val == "ry":
                    ry = float(m.group(1))
        tm = re.search(r'transform="([^"]+)"', attrs, re.I)
        mat = parse_matrix(tm.group(1) if tm else "")
        pts = []
        for i in range(25):
            a = 2 * math.pi * i / 24
            px, py = apply_matrix(mat, cx + rx * math.cos(a), cy + ry * math.sin(a))
            pts.append((px, py))
        paths.append((pts, True))
    return paths, vb_w, vb_h


def extract_all_paths(body):
    m = re.search(r"<svg\b([^>]*)>(.*)</svg>", body, re.I | re.S)
    if not m:
        return []
    vb_w = vb_h = 100.0
    vm = re.search(r'viewBox="([^"]+)"', m.group(1), re.I)
    if vm:
        p = vm.group(1).split()
        if len(p) >= 4:
            vb_w, vb_h = float(p[2]), float(p[3])
    inner = m.group(2)
    loops = []
    for d in re.findall(r'\bd="([^"]+)"', inner, re.I):
        for sub in split_subpaths(d):
            v, c = parse_path_raw(sub)
            if len(v) >= 2:
                loops.append((v, c))
    ell, ew, eh = ellipse_paths(inner, vb_w, vb_h)
    loops.extend(ell)
    return loops, ew, eh


def main():
    # Instagram: multiple subpaths in one <path>
    ig = fetch("https://cdn.shopify.com/s/files/1/0746/3672/2449/files/a_WM81_Instagram_20v4.svg")
    ig_paths, vbw, vbh = extract_all_paths(ig)
    assert len(ig_paths) >= 4, f"instagram expected >=4 subpaths, got {len(ig_paths)}"
    for v, _ in ig_paths:
        mm = map_to_box(v, vbw, vbh, ICON_BOX)
        assert in_canvas(mm), "instagram subpath off canvas"
    print(f"OK: Instagram → {len(ig_paths)} path loops inside canvas")

    # Line type 5: ellipses only
    l5 = fetch("https://cdn.shopify.com/s/files/1/0746/3672/2449/files/a_svLa_Type_5.svg")
    l5_paths, vbw, vbh = extract_all_paths(l5)
    assert len(l5_paths) >= 30, f"line type 5 expected many ellipses, got {len(l5_paths)}"
    visible = 0
    for v, _ in l5_paths:
        mm = map_to_box(v, vbw, vbh, LINE_BOX)
        if in_canvas(mm):
            visible += 1
    assert visible >= 20, f"line5 only {visible} ellipses on canvas"
    print(f"OK: Line type 5 → {len(l5_paths)} ellipses, {visible} visible in line box")

    # Line type 1: classic path
    l1 = fetch("https://cdn.shopify.com/s/files/1/0746/3672/2449/files/a_ndWL_Line1.svg")
    l1_paths, vbw, vbh = extract_all_paths(l1)
    assert len(l1_paths) >= 1
    mm = map_to_box(l1_paths[0][0], vbw, vbh, LINE_BOX)
    assert in_canvas(mm)
    print("OK: Line type 1 path maps inside canvas")

    # Uniform scale: wide line box must not stretch a square path into a rectangle
    square_d = "M 0 0 L 100 0 L 100 100 L 0 100 Z"
    sq_v, _ = parse_path_raw(square_d)
    mm_stretch = map_to_box(sq_v, 100, 100, LINE_BOX, uniform=False)
    mm_fit = map_to_box(sq_v, 100, 100, LINE_BOX, uniform=True)
    xs = [p[0] for p in mm_fit]
    ys = [p[1] for p in mm_fit]
    w_fit = max(xs) - min(xs)
    h_fit = max(ys) - min(ys)
    assert abs(w_fit - h_fit) < 0.5, f"uniform fit should stay square-ish, got {w_fit}x{h_fit}"
    xs2 = [p[0] for p in mm_stretch]
    ys2 = [p[1] for p in mm_stretch]
    w_st = max(xs2) - min(xs2)
    h_st = max(ys2) - min(ys2)
    assert w_st > h_st * 2, "non-uniform stretch should elongate square in wide box"
    print("OK: Uniform scale preserves aspect ratio in line box")

    print("\nAll export smoke checks passed.")


if __name__ == "__main__":
    main()
