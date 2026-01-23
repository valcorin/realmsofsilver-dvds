#!/usr/bin/env python3
"""
generate_pdf.py

Convert an HTML file from public/travel to PDF format.

Usage:
  python scripts/generate_pdf.py <html-file> [--output <pdf-file>]

Example:
  python scripts/generate_pdf.py public/travel/glencoe-to-oban-walking-trip.html
  python scripts/generate_pdf.py public/travel/glencoe-to-oban-walking-trip.html --output glencoe-trip.pdf

This script uses weasyprint for HTML to PDF conversion.
"""
from pathlib import Path
import argparse
import sys


def generate_pdf(html_path: Path, output_path: Path = None):
    """Convert an HTML file to PDF using weasyprint."""
    try:
        from weasyprint import HTML
    except ImportError:
        print("Error: weasyprint is not installed.", file=sys.stderr)
        print("Install it with: pip install weasyprint", file=sys.stderr)
        sys.exit(1)
    
    if not html_path.exists():
        print(f"Error: HTML file not found: {html_path}", file=sys.stderr)
        sys.exit(1)
    
    # If no output path specified, use the same name with .pdf extension
    if output_path is None:
        output_path = html_path.with_suffix('.pdf')
    
    print(f"Converting {html_path} to PDF...")
    print(f"Output: {output_path}")
    
    try:
        # Read the HTML file and convert to PDF
        HTML(filename=str(html_path)).write_pdf(str(output_path))
        print(f"✓ Successfully generated PDF: {output_path}")
        print(f"  File size: {output_path.stat().st_size / 1024:.1f} KB")
        return True
    except Exception as e:
        print(f"Error generating PDF: {e}", file=sys.stderr)
        return False


def main():
    parser = argparse.ArgumentParser(
        description="Convert travel HTML files to PDF format"
    )
    parser.add_argument(
        "html_file",
        type=str,
        help="Path to the HTML file to convert"
    )
    parser.add_argument(
        "--output", "-o",
        type=str,
        help="Output PDF file path (default: same as input with .pdf extension)"
    )
    
    args = parser.parse_args()
    
    html_path = Path(args.html_file)
    output_path = Path(args.output) if args.output else None
    
    success = generate_pdf(html_path, output_path)
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
