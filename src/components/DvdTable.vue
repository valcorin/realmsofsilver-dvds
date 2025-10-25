<template>
  <div class="dvd-table-container">
    <h2>DVD Collection</h2>
    <div class="table-wrapper">
      <table class="dvd-table">
        <thead>
          <tr>
            <th @click="sortBy('title')">
              Title
              <span v-if="sortColumn === 'title'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('year')">
              Year
              <span v-if="sortColumn === 'year'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="sortBy('director')">
              Director
              <span v-if="sortColumn === 'director'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
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
            :class="{ 'selected': selectedDvd?.id === dvd.id }"
            @click="selectDvd(dvd)"
            class="clickable-row"
          >
            <td>{{ dvd.title }}</td>
            <td>{{ dvd.year }}</td>
            <td>{{ dvd.director }}</td>
            <td>{{ dvd.genre }}</td>
            <td>
              <button @click.stop="editDvd(dvd)" class="btn-edit">Edit</button>
              <button @click.stop="viewDetails(dvd)" class="btn-view">View</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="table-info">
      <p>Total DVDs: {{ dvds.length }}</p>
      <p class="hint">Click on a row to select it, or use the buttons to view/edit details</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  dvds: {
    type: Array,
    required: true
  }
});

const emit = defineEmits(['select-dvd', 'edit-dvd']);

const selectedDvd = ref(null);
const sortColumn = ref('title');
const sortDirection = ref('asc');

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
  selectedDvd.value = dvd;
  emit('select-dvd', dvd);
};

const editDvd = (dvd) => {
  selectedDvd.value = dvd;
  emit('edit-dvd', dvd);
};

const viewDetails = (dvd) => {
  selectedDvd.value = dvd;
  emit('select-dvd', dvd);
};
</script>

<style scoped>
.dvd-table-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

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
}

.clickable-row {
  cursor: pointer;
  transition: background-color 0.2s;
}

.clickable-row:hover {
  background-color: #f5f5f5;
}

.clickable-row.selected {
  background-color: #e3f2fd;
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
</style>
