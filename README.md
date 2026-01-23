# Realms of Silver DVD Collection

A Vue 3 web application for managing and browsing your DVD collection with an interactive table interface and detailed form views.

## Features

- 📊 **Interactive Table View**: Browse your DVD collection in a sortable, clickable table
- 🔍 **Detailed Form View**: View and edit complete DVD information
- 🎨 **Modern UI**: Beautiful gradient design with responsive layout
- 📝 **Edit Capability**: Update DVD details with an intuitive form interface
- 🔄 **Sortable Columns**: Click column headers to sort by title, year, director, or genre
- 📱 **Responsive Design**: Works on desktop and mobile devices

## Project Structure

```
realmsofsilver-dvds/
├── src/
│   ├── components/
│   │   ├── DvdTable.vue    # Interactive DVD table component
│   │   └── DvdForm.vue     # DVD detail/edit form component
│   ├── data/
│   │   └── dvds.js         # DVD data (replace with your actual data)
│   ├── App.vue             # Main application component
│   ├── main.js             # Application entry point
│   └── style.css           # Global styles
├── public/                 # Static assets
├── index.html             # HTML template
├── vite.config.js         # Vite configuration
└── package.json           # Project dependencies
```

## Prerequisites

- Node.js (version 14 or higher)
- npm (comes with Node.js)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/valcorin/realmsofsilver-dvds.git
   cd realmsofsilver-dvds
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

## Usage

### Development Mode

Start the development server with hot reload:

```bash
npm run dev
```

The application will be available at `http://localhost:5173`

### Production Build

Build the application for production:

```bash
npm run build
```

The built files will be in the `dist/` directory.

### Preview Production Build

Preview the production build locally:

```bash
npm run preview
```

## Adding Your DVD Data

To populate the application with your actual DVD collection:

1. Open `src/data/dvds.js`
2. Replace the sample data with your DVD information
3. Each DVD should follow this structure:

```javascript
{
  id: 1,                    // Unique identifier
  title: "Movie Title",     // DVD title
  year: 2024,              // Release year
  director: "Director",     // Director name
  genre: "Genre",          // Genre(s)
  runtime: "120 min",      // Runtime
  format: "DVD",           // Format (DVD/Blu-ray/4K UHD/Digital)
  condition: "Excellent",  // Condition (Excellent/Very Good/Good/Fair/Poor)
  notes: "Optional notes"  // Additional notes
}
```

## How to Use the Application

### Browsing DVDs
- View all DVDs in the table format
- Click column headers (Title, Year, Director, Genre) to sort
- Click on any row to select it

### Viewing DVD Details
- Click the green "View" button on any DVD row
- A detailed form will appear showing all DVD information
- Click "Close" or the X button to dismiss the form

### Editing DVDs
- Click the blue "Edit" button on any DVD row, OR
- Open a DVD in view mode and click the "Edit" button
- Modify any fields in the form
- Click "Save" to save changes or "Cancel" to discard
- Changes are stored in the application state (client-side only)

## Technology Stack

- **Vue 3**: Progressive JavaScript framework
- **Vite**: Next-generation frontend build tool
- **JavaScript**: Modern ES6+ syntax
- **CSS**: Custom styles with gradients and transitions

## Browser Compatibility

Works in all modern browsers that support ES6+:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)

## Development

### Project Commands

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build

### Customization

**Colors**: Edit the gradient colors in `src/App.vue` and component styles
**Layout**: Modify component styles in respective `.vue` files
**Data Fields**: Update the DVD structure in `src/data/dvds.js` and corresponding component templates

## Travel Pages PDF Generation

The repository includes travel HTML pages in the `public/travel` directory. You can generate PDF versions of these pages:

### Generate PDF for Glencoe Trip

```bash
npm run generate-pdf:glencoe
```

Or use the Python script directly:

```bash
python scripts/generate_pdf.py public/travel/glencoe-to-oban-walking-trip.html
```

### Requirements

- Python 3.6+
- weasyprint library: `pip install weasyprint`

See `scripts/README_PDF.md` for more details.

## Future Enhancements

Potential features to add:
- Backend integration for persistent storage
- Search and filter functionality
- Image uploads for DVD covers
- Export to CSV/JSON
- Print functionality
- User authentication

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open source and available under the MIT License.

## Contact

Maintainer: valcorin
Repository: https://github.com/valcorin/realmsofsilver-dvds

---

Built with ❤️ using Vue 3 and Vite
