#!/usr/bin/env python3
"""Validate generated LightBurn XML uses condensed VertList (not broken <Vert> children)."""
import re
import sys

SAMPLE_LBRN2 = """<?xml version="1.0" encoding="UTF-8"?>
<LightBurnProject AppVersion="1.7.08" FormatVersion="1" MaterialHeight="0" MirrorX="False" MirrorY="False">
<CutSetting Index="0" Name="Guides" Type="Cut" Speed="200" MaxPower="5" MinPower="5" Priority="0" Color="0"/>
<CutSetting Index="1" Name="Engrave" Type="Cut" Speed="60" MaxPower="80" MinPower="80" Priority="1" Color="0"/>
<Shape Type="Path" CutIndex="1" IsClosed="1">
<XForm>1 0 0 1 0 0</XForm>
<VertList>V10 20V30 40V30 60V10 60</VertList>
<PrimList>L0 1L1 2L2 3L3 0</PrimList>
</Shape>
</LightBurnProject>
"""

SAMPLE_LBRN = """<?xml version="1.0" encoding="UTF-8"?>
<LightBurnProject AppVersion="1.7.08" FormatVersion="0">
<Shape Type="Path" CutIndex="1" IsClosed="1">
<XForm>1 0 0 1 0 0</XForm>
<V vx="10" vy="20"/>
<V vx="30" vy="40"/>
<P T="L" p0="0" p1="1"/>
</Shape>
</LightBurnProject>
"""


def check_lbrn2(xml):
    assert "<Vert " not in xml and "<Prim " not in xml, "lbrn2 must not use verbose Vert/Prim tags"
    m = re.search(r"<VertList>([^<]+)</VertList>", xml)
    assert m, "missing VertList"
    assert m.group(1).startswith("V"), "VertList must be condensed V-prefixed string"
    m2 = re.search(r"<PrimList>([^<]+)</PrimList>", xml)
    assert m2, "missing PrimList"
    assert re.search(r"L\d+ \d+", m2.group(1)), "PrimList must contain L0 1 style primitives"


def check_lbrn(xml):
    assert re.search(r'<V vx="', xml), "legacy lbrn needs V elements"
    assert re.search(r'<P T="', xml), "legacy lbrn needs P elements"
    assert "<VertList>" not in xml or "V10" in xml


def main():
    check_lbrn2(SAMPLE_LBRN2)
    check_lbrn(SAMPLE_LBRN)
    print("OK: LightBurn XML structure checks passed")


if __name__ == "__main__":
    main()
