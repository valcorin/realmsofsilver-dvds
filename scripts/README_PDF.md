# PDF Generation for Travel Pages

This directory contains a script to generate PDF versions of the travel HTML pages.

## Requirements

- Python 3.6+
- weasyprint library

## Installation

Install the required Python library:

```bash
pip install weasyprint
```

## Usage

### Generate PDF for a specific HTML file

```bash
python scripts/generate_pdf.py public/travel/glencoe-to-oban-walking-trip.html
```

Or use the npm script:

```bash
npm run generate-pdf:glencoe
```

### Custom output path

```bash
python scripts/generate_pdf.py public/travel/glencoe-to-oban-walking-trip.html --output my-trip.pdf
```

## Features

- Converts HTML to high-quality PDF format
- Preserves all styling and layout from the HTML
- Automatically handles relative paths and embedded styles
- Generates PDFs suitable for printing or sharing

## Output

The generated PDF will be saved in the same directory as the source HTML file with a `.pdf` extension, unless a custom output path is specified.

For the Glencoe trip, the PDF is generated at:
`public/travel/glencoe-to-oban-walking-trip.pdf`
