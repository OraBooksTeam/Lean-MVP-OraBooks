#!/usr/bin/env python3
"""Remove SL-### traceability text from project files without changing logic."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PARENT = ROOT.parent

SEARCH_ROOTS = [ROOT, PARENT]
EXTS = {".php", ".ts", ".tsx", ".md", ".txt", ".json", ".jsx", ".js", ".css", ".html", ".xml", ".mjs", ".ps1", ".doc", ".py"}
SKIP_DIRS = {"node_modules", "vendor", ".git", "dist", "build", ".next", "coverage"}

SL_RE = re.compile(r"SL-\d+", re.IGNORECASE)


def in_skipped(path: Path) -> bool:
    return any(part in SKIP_DIRS for part in path.parts)


def should_process(path: Path) -> bool:
    return path.suffix.lower() in EXTS and not in_skipped(path)


def clean_text(text: str) -> str:
    if not SL_RE.search(text):
        return text

    # Range and parenthetical forms first.
    text = re.sub(r"\(\s*SL-\d+(?:\s+through\s+SL-\d+)?\s*\)", "", text, flags=re.I)
    text = re.sub(r"SL-\d+\s+through\s+SL-\d+", "", text, flags=re.I)
    text = re.sub(r"SL-\d+", "", text, flags=re.I)

    # Light punctuation/whitespace cleanup after removal.
    text = re.sub(r"\(\s*\)", "", text)
    text = re.sub(r"[ \t]+([,.;:])", r"\1", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
    text = re.sub(r" \n", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text


def main() -> int:
    changed = 0
    remaining = 0

    for base in SEARCH_ROOTS:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if not path.is_file() or not should_process(path):
                continue
            try:
                original = path.read_text(encoding="utf-8")
            except (UnicodeDecodeError, OSError):
                try:
                    original = path.read_text(encoding="latin-1")
                except OSError:
                    continue

            if not SL_RE.search(original):
                continue

            updated = clean_text(original)
            if updated != original:
                path.write_text(updated, encoding="utf-8", newline="")
                changed += 1

    for base in SEARCH_ROOTS:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if not path.is_file() or not should_process(path):
                continue
            try:
                remaining += len(SL_RE.findall(path.read_text(encoding="utf-8")))
            except OSError:
                pass

    print(f"Updated files: {changed}")
    print(f"Remaining SL- references: {remaining}")
    return 0 if remaining == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
