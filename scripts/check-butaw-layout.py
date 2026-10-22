"""Read-only PDF verification, not HOA approval. Requires PyMuPDF and preview-butaw-layout.php."""
import hashlib
import json
from pathlib import Path

import pymupdf

root = Path(__file__).resolve().parent.parent
assets = root / "resources/certificates/resibo-butaw-pdf-2026"
layout = json.loads((assets / "layout.json").read_text())
assert layout["source_format"] == "pdf"
assert hashlib.sha256((assets / "reference.pdf").read_bytes()).hexdigest() == layout["source_sha256"]
assert hashlib.sha256((assets / "reference.pdf").read_bytes()).hexdigest() == layout["sha256"]
review = root / "storage/framework/testing/butaw-layout-review"
source = pymupdf.open(assets / "reference.pdf")
blank = pymupdf.open(review / "blank.pdf")
filled = pymupdf.open(review / "filled.pdf")
draft = pymupdf.open(review / "draft.pdf")
assert len(source) == len(blank) == len(filled) == 1
assert source[0].rect == blank[0].rect == filled[0].rect == pymupdf.Rect(0, 0, 612, 792)
assert source[0].get_text().count("Monthly Dues:") == layout["visible_slips"] == 1
assert source[0].get_text() == blank[0].get_text()
original = source[0].get_pixmap(alpha=False)
unpopulated = blank[0].get_pixmap(alpha=False)
populated = filled[0].get_pixmap(alpha=False)
delta = max(abs(a - b) for a, b in zip(original.samples, unpopulated.samples))
assert delta <= 2, delta  # FPDI transparency/antialias rounding; not byte identity.
boxes = [pymupdf.Rect(f["x"] - 1, f["y"] - 1, f["x"] + f["width"] + 1, f["y"] + f["height"] + 1) for f in layout["fields"]]
changed = 0
for offset, (a, b) in enumerate(zip(unpopulated.samples, populated.samples)):
    if a != b:
        changed += 1
        pixel = offset // unpopulated.n
        point = pymupdf.Point(pixel % unpopulated.width, pixel // unpopulated.width)
        assert any(box.contains(point) for box in boxes), point
assert changed > 0
assert "José Dela Cruz" in filled[0].get_text()
assert not list(filled[0].widgets())
assert "DRAFT - NOT VALID FOR OFFICIAL USE" in draft[0].get_text()
for value in ["489.00", "October", "71", "10", "José Dela Cruz"]:
    spans = [s for b in filled[0].get_text("dict")["blocks"] if "lines" in b for line in b["lines"] for s in line["spans"] if s["text"] == value]
    assert spans and all(s["font"] == "Calibri" and abs(s["size"] - 11.04) < 0.01 for s in spans), (value, spans)
filled[0].get_pixmap(matrix=pymupdf.Matrix(2, 2), clip=pymupdf.Rect(28, 32, 313, 186)).save(review / "first-slip.png")
print(f"Letter, one original uploaded slip, unchanged background (max {delta}/255), {changed} changed channels confined to mapped blanks, exact Calibri 11.04, Unicode, watermark and no form fields verified.")
