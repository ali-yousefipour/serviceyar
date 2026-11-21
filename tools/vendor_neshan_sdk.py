#!/usr/bin/env python3
"""Stage a pinned Neshan browser SDK on the first-party host (build time only).

The upstream map tile, routing and geocoding APIs remain remote services.
Fail a release instead of silently serving an unavailable/incomplete SDK.
"""
from __future__ import annotations

import argparse
import hashlib
import re
import sys
from pathlib import Path
from urllib.parse import urljoin, urlsplit
from urllib.request import Request, urlopen

VERSION = "v1.0.8"
ORIGIN = "https://static.neshan.org"
BASE = ORIGIN + "/sdk/leaflet/v1.9.4/neshan-sdk/" + VERSION + "/"
MAX_BYTES = 4 * 1024 * 1024
CSS_REFERENCE = re.compile(r"url\\(\\s*['\\\"]?([^)'\\\"]+)['\\\"]?\\s*\\)", re.IGNORECASE)


def download(url: str) -> bytes:
    request = Request(url, headers={"User-Agent": "ServiceYar-SDK-Vendor/1.0", "Accept": "*/*"})
    with urlopen(request, timeout=40) as response:
        data = response.read(MAX_BYTES + 1)
        if len(data) > MAX_BYTES:
            raise ValueError("Upstream SDK asset exceeds the maximum supported size")
        if response.status != 200:
            raise ValueError("Unexpected upstream status for SDK asset")
        return data


def stage(output: Path) -> None:
    output.mkdir(parents=True, exist_ok=True)
    checksums = []
    for name, expected_type in (("index.js", "javascript"), ("index.css", "css")):
        data = download(BASE + name)
        start = data[:300].lstrip().lower()
        if len(data) < 150 or start.startswith((b"<!doctype", b"<html", b"<?xml")):
            raise ValueError("Upstream returned an invalid " + expected_type + " asset")
        if name.endswith(".css") and b"{" not in data:
            raise ValueError("Neshan stylesheet is missing CSS rules")
        (output / name).write_bytes(data)
        checksums.append(f"{hashlib.sha256(data).hexdigest()}  {name}")
        if name == "index.css":
            css = data.decode("utf-8")
            for raw in CSS_REFERENCE.findall(css):
                ref = raw.strip()
                if not ref or ref.startswith(("data:", "#", "blob:")):
                    continue
                resolved = urljoin(BASE, ref)
                urlparts = urlsplit(resolved)
                # Never download map tiles, cross-origin files or arbitrary URLs.
                if urlparts.netloc != "static.neshan.org" or not urlparts.path.startswith(urlsplit(BASE).path):
                    raise ValueError("Unexpected external SDK CSS asset: " + ref)
                relative = urlparts.path[len(urlsplit(BASE).path):]
                if not relative or ".." in Path(relative).parts:
                    raise ValueError("Invalid SDK asset path")
                path = output / relative
                if path.exists():
                    continue
                path.parent.mkdir(parents=True, exist_ok=True)
                payload = download(resolved)
                if not payload:
                    raise ValueError("Empty SDK CSS dependency")
                path.write_bytes(payload)
                checksums.append(f"{hashlib.sha256(payload).hexdigest()}  {relative}")
    (output / "SHA256SUMS").write_text("\n".join(checksums) + "\n", encoding="ascii")
    (output / "SOURCE.txt").write_text(
        "Neshan Leaflet browser SDK " + VERSION + "\n"
        "Source: " + BASE + "\n"
        "Build-time download only; browser requests are same-origin.\n"
        "The separate @neshan-maps-platform/leaflet npm package is published under ISC; "
        "verify distribution terms of the upstream SDK before further redistribution.\n",
        encoding="utf-8",
    )
    print("Local Neshan SDK staged:", ", ".join(c.split("  ")[1] for c in checksums))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=Path, help="Directory inside deploy-payload/assets")
    args = parser.parse_args()
    try:
        stage(args.output)
    except Exception as exc:
        print("SDK vendor failed; do not publish incomplete local map assets:", exc, file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
