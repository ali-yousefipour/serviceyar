#!/usr/bin/env python3
"""Offline contract test for the first-party Neshan asset staging step."""
from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path
from unittest.mock import patch

path = Path(__file__).resolve().parents[1] / "tools" / "vendor_neshan_sdk.py"
spec = importlib.util.spec_from_file_location("sy_neshan_vendor", path)
assert spec and spec.loader
vendor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(vendor)

fake_js = b"/* sdk */" + (b"window.testNeshanSDK=true;" * 8)
fake_css = b".map{color:#123;} .marker{background:url(images/icon.png)}" + b" /* ok */" * 22
fake_icon = b"PNG-not-a-real-map-tile"

def mocked_download(url: str) -> bytes:
    if url == vendor.BASE + "index.js":
        return fake_js
    if url == vendor.BASE + "index.css":
        return fake_css
    if url == vendor.BASE + "images/icon.png":
        return fake_icon
    raise AssertionError("Unexpected asset URL: " + url)

with tempfile.TemporaryDirectory() as directory:
    target = Path(directory) / "assets" / "vendor" / "neshan" / "v1.0.8"
    with patch.object(vendor, "download", side_effect=mocked_download):
        vendor.stage(target)
    assert (target / "index.js").read_bytes() == fake_js
    assert (target / "index.css").read_bytes() == fake_css
    assert (target / "images/icon.png").read_bytes() == fake_icon
    assert len((target / "SHA256SUMS").read_text().splitlines()) == 3
    assert vendor.VERSION in (target / "SOURCE.txt").read_text()

with tempfile.TemporaryDirectory() as directory:
    def malicious(url: str) -> bytes:
        return b"<html>server error</html>" if url.endswith("index.js") else fake_css
    with patch.object(vendor, "download", side_effect=malicious):
        try:
            vendor.stage(Path(directory))
        except ValueError:
            pass
        else:
            raise AssertionError("HTML errors must not be vendored as JS")

print("Local Neshan SDK vendoring and fail-closed checks: OK")
