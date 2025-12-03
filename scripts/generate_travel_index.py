#!/usr/bin/env python3
"""
generate_travel_index.py

Scan `public/travel` for HTML files, extract each page's <title>, and write
`public/travel/index.html` listing links to the pages. Skips `index.html`.

Usage:
  python scripts/generate_travel_index.py [--dir PATH] [--dry-run]

This script uses only the Python standard library for portability.
"""
from pathlib import Path
import re
import argparse
import html


TITLE_RE = re.compile(r"<title\s*[^>]*>(.*?)</title>", re.IGNORECASE | re.DOTALL)
OVERVIEW_RE = re.compile(r'<div[^>]+class=["\']trip-overview["\'][^>]*>(.*?)</div>', re.IGNORECASE | re.DOTALL)
P_TAG_RE = re.compile(r'<p[^>]*>(.*?)</p>', re.IGNORECASE | re.DOTALL)


def extract_title(text: str) -> str:
    m = TITLE_RE.search(text)
    if not m:
        return "(no title)"
    return html.unescape(m.group(1).strip())


def extract_overview(text: str) -> str:
    """Return the first paragraph from a `.trip-overview` div, plain text."""
    m = OVERVIEW_RE.search(text)
    if not m:
        return ""
    inner = m.group(1)
    p = P_TAG_RE.search(inner)
    if not p:
        # fallback: strip tags from inner content
        stripped = re.sub(r'<[^>]+>', '', inner).strip()
        return html.unescape(stripped)
    # return first paragraph text
    para = p.group(1).strip()
    para = re.sub(r'<[^>]+>', '', para)
    # collapse whitespace
    para = re.sub(r'\s+', ' ', para)
    return html.unescape(para)


TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Travel Index — Realms of Silver</title>
    <style>
        body{font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f5f7fb;color:#111;margin:0;padding:36px}
        .wrap{max-width:900px;margin:0 auto;background:#fff;padding:28px;border-radius:10px;box-shadow:0 8px 30px rgba(16,24,40,.08)}
        h1{font-size:1.6rem;margin-bottom:6px}
        p.lead{color:#556;padding-bottom:12px;margin-top:0}
        ul.files{list-style:none;padding-left:0}
        ul.files li{padding:12px 14px;border-radius:8px;margin:8px 0;background:linear-gradient(180deg,#ffffff,#fbfcff);box-shadow:0 1px 0 rgba(16,24,40,.03)}
        a.file{color:#0b66ff;text-decoration:none;font-weight:600}
        a.file:hover{text-decoration:underline}
        .meta{color:#556;font-size:.95rem;margin-top:6px}
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Travel Pages</h1>
        <p class="lead">Quick index of the travel pages in this folder.</p>
        <ul class="files">
{items}
        </ul>
    </div>
</body>
</html>
"""


def build_index(dir_path: Path, dry_run: bool = False) -> str:
    files = sorted(dir_path.glob("*.html"))
    items = []
    for p in files:
        if p.name.lower() == "index.html":
            continue
        try:
            text = p.read_text(encoding="utf-8")
        except Exception:
            text = p.read_text(encoding="latin-1")
        title = extract_title(text)
        overview = extract_overview(text)
        meta_text = html.escape(overview) if overview else html.escape(p.name)
        item = (
            f'            <li>\n'
            f'                <a class="file" href="{p.name}">{html.escape(title)}</a>\n'
            f'                <div class="meta">{meta_text}</div>\n'
            f'            </li>'
        )
        items.append(item)

    body = "\n".join(items)
    # Use simple replace instead of str.format to avoid accidental
    # interpolation of braces inside the CSS/template.
    full = TEMPLATE.replace("{items}", body)
    if dry_run:
        return full

    out_path = dir_path / "index.html"
    out_path.write_text(full, encoding="utf-8")
    return str(out_path)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dir", default="public/travel", help="Directory containing travel HTML files")
    ap.add_argument("--dry-run", action="store_true", help="Print generated HTML to stdout instead of writing file")
    args = ap.parse_args()

    dirp = Path(args.dir)
    if not dirp.exists() or not dirp.is_dir():
        raise SystemExit(f"Directory not found: {dirp}")

    res = build_index(dirp, dry_run=args.dry_run)
    if args.dry_run:
        print(res)
    else:
        print(f"Wrote: {res}")


if __name__ == '__main__':
    main()
