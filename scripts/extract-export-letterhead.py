"""Extract reviewed Word-PDF artwork; never edit the source or approve certificates.

Requires isolated development-only PyMuPDF. Refuses existing output assets.
Usage: python scripts/extract-export-letterhead.py source.docx word-export.pdf
"""
import hashlib
import json
import sys
from pathlib import Path

import pymupdf

root = Path(__file__).resolve().parent.parent
source, reference = (Path(value).resolve() for value in sys.argv[1:3])
source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
assert source_hash == "9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436", "Unexpected original DOCX"
destination = root / "resources/exports/cohabitation-letterhead"
assert not (destination / "letterhead.png").exists(), "Refusing to overwrite reviewed artwork"
assert not (destination / "manifest.json").exists(), "Refusing to overwrite provenance"
document = pymupdf.open(reference)
assert len(document) == 1 and list(document[0].rect) == [0, 0, 612, 792]
crop = pymupdf.Rect(36, 28, 576, 128)
text = document[0].get_text(clip=crop)
assert "SOUTHVILLE HOMEOWNERS ASSOCIATION INC." in text
assert "13466-R4A-06-12" in text
assert "CERTIFICATE" not in text and "JOSEPHINE" not in text
destination.mkdir(parents=True, exist_ok=True)
image = document[0].get_pixmap(dpi=600, clip=crop, alpha=False)
image.save(destination / "letterhead.png")
manifest = {
    "source_filename": source.name,
    "source_sha256": source_hash,
    "word_pdf_sha256": hashlib.sha256(reference.read_bytes()).hexdigest(),
    "page_points": [612, 792],
    "crop_points": list(crop),
    "dpi": 600,
    "width": image.width,
    "height": image.height,
    "sha256": hashlib.sha256((destination / "letterhead.png").read_bytes()).hexdigest(),
    "tool": "PyMuPDF " + pymupdf.VersionBind,
    "review_date": "2026-10-23",
}
(destination / "manifest.json").write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")
assert hashlib.sha256(source.read_bytes()).hexdigest() == source_hash
print(json.dumps(manifest))
