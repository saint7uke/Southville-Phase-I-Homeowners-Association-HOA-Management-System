"""Optional engineering check: requires PyMuPDF; never modifies the source PDFs.

First run `php scripts/preview-residency-layout.php`. This is not HOA approval.
"""
import json
from pathlib import Path

import pymupdf

root = Path(__file__).resolve().parent.parent
review = root / "storage/framework/testing/residency-layout-review"
layout = json.loads((root / "resources/certificates/residency-2026/layout.json").read_text())
source = pymupdf.open(root / "resources/certificates/residency-2026/reference.pdf")
blank = pymupdf.open(review / "blank.pdf")
filled = pymupdf.open(review / "filled.pdf")
draft = pymupdf.open(review / "draft.pdf")
assert len(source) == len(blank) == len(filled) == len(layout["pages"])
for index in range(len(source)):
    assert source[index].rect == blank[index].rect == filled[index].rect
    assert source[index].get_text() == blank[index].get_text()
    original_pixels = source[index].get_pixmap(alpha=False).samples
    blank_map = blank[index].get_pixmap(alpha=False)
    blank_pixels = blank_map.samples
    filled_pixels = filled[index].get_pixmap(alpha=False).samples
    # FPDI's transparency group produces <=2/255 antialias/color rounding,
    # with the same text, geometry and images. Do not call this byte/pixel identity.
    maximum_reference_delta = max(abs(a - b) for a, b in zip(original_pixels, blank_pixels))
    assert maximum_reference_delta <= 2, maximum_reference_delta
    boxes = [pymupdf.Rect(f["x"] - 2, f["y"] - 2, f["x"] + f["width"] + 2,
                          f["y"] + f["height"] + 2)
             for f in layout["fields"] if f["page"] == index + 1]
    changed_channels = 0
    for offset, (a, b) in enumerate(zip(blank_pixels, filled_pixels)):
        if a == b:
            continue
        changed_channels += 1
        pixel = offset // blank_map.n
        point = pymupdf.Point(pixel % blank_map.width, pixel // blank_map.width)
        assert any(box.contains(point) for box in boxes), point
    assert not list(filled[index].widgets())
    assert "Powered by TCPDF" not in filled[index].get_text()
    assert "DRAFT - NOT VALID FOR OFFICIAL USE" in draft[index].get_text()
    print(f"Page {index + 1}: reference delta <= {maximum_reference_delta}/255; "
          f"{changed_channels} changed channels confined to blank regions; no form widgets.")
assert "José Dela Cruz" in filled[0].get_text()
populated_spans = [span for block in filled[0].get_text("dict")["blocks"] if "lines" in block
                   for line in block["lines"] for span in line["spans"] if span["text"] == "José Dela Cruz"]
assert populated_spans and all("TimesNewRoman" in span["font"] and "Bold" in span["font"]
                               and abs(span["size"] - 12) < 0.01 for span in populated_spans)
print("Unicode populated text, Letter size, unchanged background and draft watermark verified.")
