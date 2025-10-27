<template>
  <div class="dvd-table-container">
    <div class="table-header">
      <h2>DVD Collection</h2>

      <div class="search-wrap">
        <input
          ref="searchInput"
          v-model="searchQuery"
          @input="onSearchInput"
          type="search"
          placeholder="Search titles, actors, directors..."
          aria-label="Search DVDs"
          class="search-input"
        />
      </div>

  <button class="btn-new" @click.stop="createDvd" :disabled="!props.isAdmin" :aria-disabled="!props.isAdmin">New</button>
    </div>
    <div class="table-wrapper">
      <table class="dvd-table">
        <thead>
          <tr>
            <th>Cover</th>
            <th @click="sortBy('title')">
              Title
              <span v-if="sortColumn === 'title'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('year')">
              Year
              <span v-if="sortColumn === 'year'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('type')">
              Type
              <span v-if="sortColumn === 'type'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('director')">
              Directors
              <span v-if="sortColumn === 'director'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('actors')">
              Actors
              <span v-if="sortColumn === 'actors'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('genre')">
              Genre
              <span v-if="sortColumn === 'genre'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="dvd in sortedDvds" 
            :key="dvd.id"
            @click="selectDvd(dvd)"
            class="clickable-row"
          >
            <td class="cover-cell">
              <img 
                v-if="getDvdImageUrl(dvd)" 
                :src="getDvdImageUrl(dvd)" 
                :alt="dvd.title"
                class="cover-thumbnail"
                @error="handleImageError"
              />
              <div v-else class="no-cover">📀</div>
            </td>
            <td>{{ dvd.title }}</td>
            <td>{{ dvd.year }}</td>
            <td>{{ displayType(dvd.type || dvd.format) }}</td>
            <td>{{ dvd.directors || dvd.director }}</td>
            <td>{{ dvd.actors || dvd.stars }}</td>
            <td>{{ dvd.genre }}</td>
            
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import dvdApi from '../services/dvdApi.js';

const props = defineProps({
  dvds: {
    type: Array,
    required: true
  },
  pagination: {
    type: Object,
    default: () => ({})
  },
  // current sort state is driven by the parent so the table only reflects it
  sortColumn: {
    type: String,
    default: 'title'
  },
  sortDirection: {
    type: String,
    default: 'asc'
  }
  ,
  isAdmin: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['select-dvd', 'edit-dvd', 'create-dvd', 'search']);

const searchQuery = ref('');
let searchDebounce = null;
const searchInput = ref(null);

const onSearchInput = () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(async () => {
    emit('search', searchQuery.value.trim());
    // keep focus on the input in case re-render/parent updates cause blur
    await nextTick();
    if (searchInput.value && typeof searchInput.value.focus === 'function') {
      searchInput.value.focus();
    }
  }, 300);
};

// Note: visual sort state comes from the parent via props.sortColumn / props.sortDirection.

// Helper function to get DVD image URL
const getDvdImageUrl = (dvd) => {
  if (dvd && dvd.image) {
    return dvdApi.getImageUrl(dvd.image);
  }
  return null;
};

const handleImageError = (event) => {
  // Hide broken images
  event.target.style.display = 'none';
};

// Server-side sorting: don't sort the page locally. The parent will request sorted pages.
const sortedDvds = computed(() => props.dvds);

const sortBy = (column) => {
  // compute the new direction based on the parent's current state
  const currentCol = props.sortColumn || 'title';
  const currentDir = props.sortDirection || 'asc';
  const newDir = (currentCol === column && currentDir === 'asc') ? 'desc' : 'asc';
  // Ask parent to reload data using server-side sorting
  emit('sort', { column, direction: newDir });
};

const selectDvd = (dvd) => {
  emit('select-dvd', dvd);
};

const editDvd = (dvd) => {
  if (!props.isAdmin) {
    console.warn('Edit action blocked: admin required');
    return;
  }
  emit('edit-dvd', dvd);
};

const createDvd = () => {
  if (!props.isAdmin) {
    console.warn('Create action blocked: admin required');
    return;
  }
  emit('create-dvd');
};

const viewDetails = (dvd) => {
  emit('select-dvd', dvd);
};

// Map internal type/format codes to user-friendly labels
const TYPE_LABELS = {
  'DVD': 'DVD',
  'BLU': 'Blu-ray',
  '4K': '4K UHD',
  'DIG': 'Digital',
  'BCK': 'Backup',
  'VHS': 'VHS'
};

const displayType = (code) => {
  if (!code && code !== 0) return '';
  const s = String(code).trim();
  // If the value already looks like a friendly label (contains spaces or lowercase letters), show as-is
  if (/\s|[a-z]/.test(s)) return s;
  const key = s.toUpperCase();
  return TYPE_LABELS[key] || s;
};
</script>

<style scoped>
.dvd-table-container {
  width: 100%;
  /* widen the table area on large screens while keeping a sensible cap */
  max-width: min(1800px, 96vw);
  margin: 0 auto;
  padding: 12px 8px; /* reduce side padding so table uses more horizontal space */
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

/* Actions column removed - per-row Edit/View buttons hidden */

/* Search input centered between title and New button */
.table-header .search-wrap {
  flex: 1 1 360px;
  display: flex;
  justify-content: center;
  padding: 0 12px;
}

.table-header .search-input {
  width: 100%;
  max-width: 520px;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
  font-size: 0.95rem;
}

@media (max-width: 640px) {
  .table-header .search-wrap { display: none; }
}

.btn-new {
  background: #22c55e;
  color: white;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.btn-new:hover { background: #16a34a; }

h2 {
  color: #2c3e50;
  margin-bottom: 20px;
}

.table-wrapper {
  overflow-x: auto;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-radius: 8px;
}

.dvd-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.dvd-table th {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 8px 12px;
  text-align: left;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  transition: background 0.3s;
  vertical-align: middle;
}

/* actions-header removed */

.dvd-table th:hover {
  opacity: 0.9;
}

.dvd-table th span {
  margin-left: 5px;
  font-size: 0.8em;
}

.dvd-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e0e0e0;
  color: #2c3e50;
  font-weight: 500;
  vertical-align: middle;
  line-height: 1.25;
  white-space: normal; /* allow wrapping but keep rows tight */
}

.clickable-row {
  cursor: pointer;
  transition: background-color 0.2s;
  color: #2c3e50;
}

.clickable-row:hover {
  background-color: #f5f5f5;
  color: #1a202c;
}

.clickable-row.selected {
  background-color: #e3f2fd;
  color: #1565c0;
}

.btn-edit, .btn-view {
  padding: 6px 10px;
  margin: 0 4px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9em;
  transition: all 0.3s;
  line-height: 1;
}

.btn-edit {
  background-color: #667eea;
  color: white;
}

.btn-edit:hover {
  background-color: #5568d3;
}

.btn-view {
  background-color: #48bb78;
  color: white;
}

.btn-view:hover {
  background-color: #38a169;
}

.table-info {
  margin-top: 15px;
  padding: 10px;
  background-color: #f9f9f9;
  border-radius: 4px;
}

.table-info p {
  margin: 5px 0;
  color: #666;
}

.hint {
  font-style: italic;
  font-size: 0.9em;
}

/* Cover image styles */
.cover-cell {
  width: 56px;
  text-align: center;
  padding: 6px 8px !important;
}

.cover-thumbnail {
  width: 36px;
  height: 54px; /* constrained to keep row heights consistent */
  object-fit: cover;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
  transition: transform 0.15s;
  display: inline-block;
  vertical-align: middle;
}

.cover-thumbnail:hover {
  transform: scale(1.06);
}

.no-cover {
  width: 40px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f0f0;
  border-radius: 4px;
  color: #999;
  font-size: 20px;
  border: 1px dashed #ccc;
}

@media (max-width: 768px) {
  .cover-cell {
    width: 40px;
  }
  
  .cover-thumbnail {
    width: 32px;
    height: 48px;
  }
  
  .no-cover {
    width: 32px;
    height: 48px;
    font-size: 16px;
  }
}
</style>
