<template>
  <div class="dvd-table-container">
    <div class="table-header">
  <h2>DVD Collection</h2>
  <button class="btn-new" @click.stop="createDvd">New</button>
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
            <th>Actions</th>
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
            <td>{{ dvd.directors || dvd.director }}</td>
            <td>{{ dvd.actors || dvd.stars }}</td>
            <td>{{ dvd.genre }}</td>
            <td>
              <button @click.stop="editDvd(dvd)" class="btn-edit">Edit</button>
              <button @click.stop="viewDetails(dvd)" class="btn-view">View</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import dvdApi from '../services/dvdApi.js';

const props = defineProps({
  dvds: {
    type: Array,
    required: true
  },
  pagination: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['select-dvd', 'edit-dvd', 'create-dvd']);

const sortColumn = ref('title');
const sortDirection = ref('asc');

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

const sortedDvds = computed(() => {
  const sorted = [...props.dvds].sort((a, b) => {
    const aVal = a[sortColumn.value];
    const bVal = b[sortColumn.value];
    
    if (typeof aVal === 'string') {
      return sortDirection.value === 'asc' 
        ? aVal.localeCompare(bVal)
        : bVal.localeCompare(aVal);
    }
    
    return sortDirection.value === 'asc' 
      ? aVal - bVal
      : bVal - aVal;
  });
  
  return sorted;
});

const sortBy = (column) => {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortDirection.value = 'asc';
  }
};

const selectDvd = (dvd) => {
  emit('select-dvd', dvd);
};

const editDvd = (dvd) => {
  emit('edit-dvd', dvd);
};

const createDvd = () => {
  emit('create-dvd');
};

const viewDetails = (dvd) => {
  emit('select-dvd', dvd);
};
</script>

<style scoped>
.dvd-table-container {
  width: 100%;
  max-width: 1400px; /* widened on desktop */
  margin: 0 auto;
  padding: 20px;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
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
  padding: 12px;
  text-align: left;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  transition: background 0.3s;
}

.dvd-table th:hover {
  opacity: 0.9;
}

.dvd-table th span {
  margin-left: 5px;
  font-size: 0.8em;
}

.dvd-table td {
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
  color: #2c3e50;
  font-weight: 500;
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
  padding: 6px 12px;
  margin-right: 5px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9em;
  transition: all 0.3s;
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
  width: 50px;
  text-align: center;
  padding: 8px !important;
}

.cover-thumbnail {
  width: 40px;
  height: 60px;
  object-fit: cover;
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}

.cover-thumbnail:hover {
  transform: scale(1.1);
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
