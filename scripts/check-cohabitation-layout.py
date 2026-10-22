"""File-only engineering checks. Requires PyMuPDF; never approves an HOA source."""
import hashlib
import json
import sys
import zipfile
from pathlib import Path

import pymupdf

root = Path(__file__).resolve().parent.parent
assets = root / "resources/certificates/cohabitation-2026"
review = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else root / "storage/framework/testing/cohabitation-phase-review"
phase_review = root / "storage/framework/testing/cohabitation-phase-review"
history = assets / "history/phase-only-20261021"
layout = json.loads((assets / "layout.json").read_text(encoding="utf-8"))
with zipfile.ZipFile(assets / "original-unadjusted.docx") as before, zipfile.ZipFile(assets / "original.docx") as after:
    assert before.namelist() == after.namelist()
    for name in before.namelist():
        expected = before.read(name)
        if name == "word/document.xml":
            old = b"Southville 2 Phase 1-A </w:t>"
            assert expected.count(old) == 1
            expected = expected.replace(old, b"Southville 2 Phase 1 - </w:t>")
            assert expected.count(b"<w:t>D,</w:t>") == 1
            expected = expected.replace(b"<w:t>D,</w:t>", b"<w:t>D.</w:t>")
        assert after.read(name) == expected, name
assert hashlib.sha256((assets / "original-unadjusted.docx").read_bytes()).hexdigest() == layout["phase_correction"]["original_sha256"]
assert hashlib.sha256((assets / "original.docx").read_bytes()).hexdigest() == layout["source_sha256"]
assert hashlib.sha256((assets / "reference.pdf").read_bytes()).hexdigest() == layout["sha256"]
assert hashlib.sha256((history / "original.docx").read_bytes()).hexdigest() == layout["signatory_correction"]["previous_source_sha256"]
assert hashlib.sha256((history / "reference.pdf").read_bytes()).hexdigest() == layout["signatory_correction"]["previous_pdf_sha256"]
baseline = pymupdf.open(phase_review / "unadjusted-reference.pdf")
previous = pymupdf.open(history / "reference.pdf")
source = pymupdf.open(assets / "reference.pdf")
blank = pymupdf.open(review / "blank.pdf")
filled = pymupdf.open(review / "filled.pdf")
draft = pymupdf.open(review / "draft.pdf")
assert len(baseline) == len(source) == len(blank) == len(filled) == 1
assert source[0].rect == baseline[0].rect == blank[0].rect == filled[0].rect == pymupdf.Rect(0, 0, 612, 792)
assert "Southville 2 Phase 1 -" in source[0].get_text()
assert "Phase 1-A" not in source[0].get_text()
assert layout["printed_signatory_name"] == "JOSEPHINE D. ALARZAR"
assert "JOSEPHINE D. ALARZAR" in source[0].get_text()
assert previous[0].get_text().replace("JOSEPHINE D, ALARZAR", "JOSEPHINE D. ALARZAR") == source[0].get_text()
original_pixels = baseline[0].get_pixmap(alpha=False)
source_pixels = source[0].get_pixmap(alpha=False)
correction_regions = [pymupdf.Rect(150, 290, 300, 307), pymupdf.Rect(40, 613, 185, 630)]
changed_correction = 0
for offset, (a, b) in enumerate(zip(original_pixels.samples, source_pixels.samples)):
    if a != b:
        pixel = offset // source_pixels.n
        assert any(region.contains(pymupdf.Point(pixel % source_pixels.width, pixel // source_pixels.width)) for region in correction_regions)
        changed_correction += 1
assert changed_correction > 0
previous_pixels = previous[0].get_pixmap(alpha=False)
changed_signatory = 0
for offset, (a, b) in enumerate(zip(previous_pixels.samples, source_pixels.samples)):
    if a != b:
        pixel = offset // source_pixels.n
        assert correction_regions[1].contains(pymupdf.Point(pixel % source_pixels.width, pixel // source_pixels.width))
        changed_signatory += 1
assert changed_signatory > 0
assert source[0].get_text() == blank[0].get_text()
blank_pixels = blank[0].get_pixmap(alpha=False)
filled_pixels = filled[0].get_pixmap(alpha=False)
assert max(abs(a - b) for a, b in zip(source_pixels.samples, blank_pixels.samples)) <= 2
regions = [pymupdf.Rect(f["x"] - 1, f["y"] - 1, f["x"] + f["width"] + 1, f["y"] + f["height"] + 1) for f in layout["fields"]]
changed_fill = 0
for offset, (a, b) in enumerate(zip(blank_pixels.samples, filled_pixels.samples)):
    if a != b:
        pixel = offset // blank_pixels.n
        assert any(box.contains(pymupdf.Point(pixel % blank_pixels.width, pixel // blank_pixels.width)) for box in regions)
        changed_fill += 1
spans = [s for b in filled[0].get_text("dict")["blocks"] if "lines" in b for l in b["lines"] for s in l["spans"]]
names = [s for s in spans if s["text"] == "José Dela Cruz"]
assert len(names) == 2 and all(s["font"] == "ArialMT" and abs(s["size"] - 12) < 0.01 for s in names)
assert abs(names[0]["origin"][1] - 256.73) < 0.01
assert "María Santos" in filled[0].get_text()
assert sum(s["text"] == "B" for s in spans) == 2
assert "DRAFT - NOT VALID FOR OFFICIAL USE" in draft[0].get_text()
assert not list(filled[0].widgets())
assert "Powered by TCPDF" not in filled[0].get_text()
filled[0].get_pixmap(matrix=pymupdf.Matrix(1.5, 1.5)).save(review / "filled.png")
print(f"DOCX package preservation passed; {changed_correction} changed PDF channels confined to the authorized phase/signatory regions.")
print(f"Archived phase-only source preserved; {changed_signatory} changed channels confined to the signatory compared with that version.")
print(f"Letter geometry, Arial 12pt/baseline, Unicode, two registered phase values, draft watermark and no interactive fields passed.")
print(f"{changed_fill} populated channels confined to mapped blanks; no source approval or database access.")
