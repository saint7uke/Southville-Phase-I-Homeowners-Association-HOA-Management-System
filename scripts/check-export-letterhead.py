"""File-only engineering review of synthetic exports; no database or source changes.

Requires isolated development-only PyMuPDF and ExportLetterheadTest review artifacts.
"""
import hashlib
import json
from pathlib import Path

import pymupdf

root = Path(__file__).resolve().parent.parent
assets = root / "resources/exports/cohabitation-letterhead"
review = root / "storage/framework/testing/export-letterhead-20261023"
manifest = json.loads((assets / "manifest.json").read_text(encoding="utf-8"))
original = root / "resources/certificates/cohabitation-2026/original-unadjusted.docx"
assert hashlib.sha256(original.read_bytes()).hexdigest() == manifest["source_sha256"]
assert hashlib.sha256((review / "word-reference.pdf").read_bytes()).hexdigest() == manifest["word_pdf_sha256"]
assert hashlib.sha256((assets / "letterhead.png").read_bytes()).hexdigest() == manifest["sha256"]
source = pymupdf.open(review / "word-reference.pdf")
expected = source[0].get_pixmap(dpi=600, clip=pymupdf.Rect(manifest["crop_points"]), alpha=False)
actual = pymupdf.Pixmap(assets / "letterhead.png")
assert expected.samples == actual.samples, "Artwork differs from the native Word export"
results = {}
for name, count, label in [("household", 85, "Review Resident "), ("report", 65, "Review assessment ")]:
    document = pymupdf.open(review / "review" / (name + ".pdf"))
    assert len(document) > 1
    page = document[0]
    assert (page.rect.width > page.rect.height) == (name == "report")
    artwork = [image for image in page.get_image_info() if image["width"] == 4500 and image["height"] == 834]
    assert len(artwork) == 1, "Missing or repeated first-page letterhead"
    bounds = pymupdf.Rect(artwork[0]["bbox"])
    assert page.rect.contains(bounds), "Letterhead exceeds the paper"
    assert abs(bounds.width / bounds.height - 4500 / 834) < 0.01, "Artwork proportions changed"
    assert all(block[1] >= bounds.y1 - 1 for block in page.get_text("blocks")), "Letterhead overlaps report content"
    text = "\n".join(page.get_text() for page in document)
    for number in range(1, count + 1):
        value = str(number) if name == "household" else str(number).zfill(3)
        assert label + value in text, "Lost row: " + label + value
    assert all(not page.get_image_info() for page in list(document)[1:]), "Letterhead unexpectedly repeated"
    assert all(not list(page.widgets() or []) for page in document)
    assert "CERTIFICATE OF COHABILITATION" not in text and "JOSEPHINE" not in text
    document[0].get_pixmap(dpi=130).save(review / "review" / (name + "-first-page.png"))
    document[-1].get_pixmap(dpi=130).save(review / "review" / (name + "-last-page.png"))
    results[name] = {"pages": len(document), "verified_rows": count, "header_bounds": list(bounds)}
print(json.dumps({"native_word_crop": "identical decoded pixels", "exports": results}))
