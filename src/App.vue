<script setup>
import { ref, onMounted } from 'vue';
import DvdTable from './components/DvdTable.vue';
import DvdForm from './components/DvdForm.vue';
import dvdApi from './services/dvdApi.js';

const dvds = ref([]);
const searchTerm = ref('');
const pagination = ref({});
const selectedDvd = ref(null);
const editMode = ref(false);
const loading = ref(true);
const error = ref(null);
const currentPage = ref(1);
const itemsPerPage = ref(10);
const sortColumn = ref('title');
const sortDirection = ref('asc');
// Controller used to cancel in-flight list/search requests
let dvdsController = null;

// Load DVDs from the API when component mounts
onMounted(async () => {
  await loadDvds();
});

const loadDvds = async (page = currentPage.value, q = undefined) => {
  // If this is a typed search (q provided by caller), avoid toggling the global loading state.
  // We consider it a search when the caller passes a value (even an empty string).
  const isSearch = typeof q !== 'undefined';
  try {
    // abort previous request if still running
    if (dvdsController) {
      try { dvdsController.abort(); } catch (e) {}
      dvdsController = null;
    }
    dvdsController = new AbortController();
    const signal = dvdsController.signal;

    if (!isSearch) {
      loading.value = true;
    }
    error.value = null;
    // Pass current sort settings into the API call so server-side sorting is applied
    const response = await dvdApi.fetchDvds(page, itemsPerPage.value, q, signal, sortColumn.value, sortDirection.value);
    dvds.value = response.data || response; // Handle both new and old response formats
    pagination.value = response.pagination || {};
    currentPage.value = page;
  } catch (err) {
    if (err && err.name === 'AbortError') {
      // aborted by a newer request — don't surface an error
      return;
    }
    error.value = `Failed to load DVDs: ${err.message}`;
    console.error('Error loading DVDs:', err);
  } finally {
    if (!isSearch) {
      loading.value = false;
    }
    try { dvdsController = null; } catch (e) {}
  }
};

const handleSearch = async (query) => {
  // Server-side search: remember query and reload page 1 with q parameter (pass empty string when cleared)
  searchTerm.value = query || '';
  currentPage.value = 1;
  await loadDvds(1, searchTerm.value);
};

const nextPage = async () => {
  if (pagination.value.has_next) {
    await loadDvds(currentPage.value + 1, searchTerm.value !== '' ? searchTerm.value : undefined);
  }
};

const prevPage = async () => {
  if (pagination.value.has_prev) {
    await loadDvds(currentPage.value - 1, searchTerm.value !== '' ? searchTerm.value : undefined);
  }
};

const goToPage = async (page) => {
  if (page >= 1 && page <= pagination.value.total_pages) {
    await loadDvds(page, searchTerm.value !== '' ? searchTerm.value : undefined);
  }
};

// Handle sort events coming from the table component
const handleSort = async ({ column, direction }) => {
  // store sort state and reload page 1 with the new ordering
  sortColumn.value = column || 'title';
  sortDirection.value = direction === 'desc' ? 'desc' : 'asc';
  currentPage.value = 1;
  await loadDvds(1, searchTerm.value !== '' ? searchTerm.value : undefined);
};

const getVisiblePages = () => {
  const total = pagination.value.total_pages || 1;
  const current = currentPage.value;
  const pages = [];
  
  // Always show first page
  if (total > 1) pages.push(1);
  
  // Show pages around current page
  const start = Math.max(2, current - 2);
  const end = Math.min(total - 1, current + 2);
  
  // Add ellipsis if there's a gap
  if (start > 2) pages.push('...');
  
  // Add pages around current
  for (let i = start; i <= end; i++) {
    if (i !== 1 && i !== total) {
      pages.push(i);
    }
  }
  
  // Add ellipsis if there's a gap
  if (end < total - 1) pages.push('...');
  
  // Always show last page
  if (total > 1) pages.push(total);
  
  return pages;
};

const handleSelectDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = false;
};

const handleEditDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = true;
};

  const handleCreateDvd = () => {
  // open the form with an empty DVD object for creation
  selectedDvd.value = {
    title: '',
    year: '',
    rating: '',
    directors: [],
    director: '',
    actors: [],
    stars: '',
    genre: '',
    runtime: '',
    format: 'DVD',
    music: '',
    notes: ''
  };
  editMode.value = true;
};

const handleUpdateDvd = async (updatedDvd) => {
  try {
    let result;

    // If there's no id/dkey, treat this as a create
    if (!updatedDvd.id && !updatedDvd.dkey) {
      result = await dvdApi.createDvd(updatedDvd);
    } else {
      result = await dvdApi.updateDvd(updatedDvd);
    }

    // Refresh the current page of results from the server so the list shows the saved changes
    await loadDvds(currentPage.value);

    // Close the modal after a successful save
    selectedDvd.value = null;
    editMode.value = false;
  } catch (err) {
    error.value = `Failed to save DVD: ${err.message}`;
    console.error('Error saving DVD:', err);
  }
};

const handleClose = () => {
  selectedDvd.value = null;
  editMode.value = false;
};

const handleDeletedDvd = async (id) => {
  // Close modal and refresh list
  selectedDvd.value = null;
  editMode.value = false;
  try {
    await loadDvds(currentPage.value);
  } catch (e) {
    console.error('Error refreshing after delete', e);
  }
};

const retryLoad = () => {
  loadDvds(currentPage.value);
};
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <h1>🎬 Realms of Silver DVD Collection</h1>
      <p class="subtitle">
        Manage and browse your DVD collection
        <span v-if="pagination.total_records"> — Showing {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, pagination.total_records) }} of {{ pagination.total_records }} DVDs</span>
      </p>
    </header>

    <main class="app-main">
      <!-- Loading state -->
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner"></div>
        <p>Loading your DVD collection...</p>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="error-container">
        <div class="error-message">
          <h3>⚠️ Error Loading DVDs</h3>
          <p>{{ error }}</p>
          <button @click="retryLoad" class="retry-button">Try Again</button>
        </div>
      </div>

      <!-- Main content -->
      <template v-else>
        <!-- Pagination controls (count shown in header subtitle) -->
        
        <DvdTable 
          :dvds="dvds"
          :pagination="pagination"
          :sort-column="sortColumn"
          :sort-direction="sortDirection"
          @select-dvd="handleSelectDvd"
          @edit-dvd="handleEditDvd"
          @create-dvd="handleCreateDvd"
          @search="handleSearch"
          @sort="handleSort"
        />
        
        <!-- Pagination controls -->
        <div v-if="pagination.total_pages > 1" class="pagination-controls">
          <button 
            @click="prevPage" 
            :disabled="!pagination.has_prev"
            class="pagination-btn"
          >
            ← Previous
          </button>
          
          <div class="page-numbers">
            <button 
              v-for="page in getVisiblePages()" 
              :key="page"
              @click="goToPage(page)"
              :class="['page-btn', { active: page === currentPage }]"
            >
              {{ page }}
            </button>
          </div>
          
          <button 
            @click="nextPage" 
            :disabled="!pagination.has_next"
            class="pagination-btn"
          >
            Next →
          </button>
        </div>
        
        <DvdForm 
          v-if="selectedDvd"
          :dvd="selectedDvd"
          :edit-mode="editMode"
          @update-dvd="handleUpdateDvd"
          @close="handleClose"
          @deleted="handleDeletedDvd"
        />
      </template>
    </main>

    <footer class="app-footer">
      <p>DVD Collection Manager • Built with Vue 3 + Vite</p>
    </footer>
  </div>
</template>

<style scoped>
.app-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.app-header {
  text-align: center;
  padding: 20px 20px;
  color: white;
}

.app-header h1 {
  margin: 0;
  font-size: 2.2em;
  font-weight: 700;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
}

.subtitle {
  margin: 5px 0 0;
  font-size: 1.1em;
  opacity: 0.95;
}

.app-main {
  flex: 1;
  background: #f5f5f5;
  padding: 20px;
  border-radius: 20px 20px 0 0;
}

.app-footer {
  text-align: center;
  padding: 20px;
  color: white;
  font-size: 0.9em;
  opacity: 0.9;
}

.app-footer p {
  margin: 0;
}

@media (max-width: 768px) {
  .app-header h1 {
    font-size: 2em;
  }
  
  .subtitle {
    font-size: 1em;
  }
}

/* Loading and error states */
.loading-container, .error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-container {
  color: #e74c3c;
}

.error-message {
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  max-width: 500px;
}

.error-message h3 {
  margin: 0 0 15px 0;
  color: #e74c3c;
}

.error-message p {
  margin: 0 0 20px 0;
  color: #666;
}

.retry-button {
  background: #667eea;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
}

.retry-button:hover {
  background: #5a67d8;
}

/* Pagination styles */
.pagination-info {
  text-align: center;
  margin-bottom: 20px;
  color: #666;
  font-size: 0.9em;
}

.pagination-info p {
  margin: 0;
  padding: 10px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.pagination-controls {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  margin: 30px 0;
  flex-wrap: wrap;
}

.pagination-btn {
  background: white;
  border: 2px solid #667eea;
  color: #667eea;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s;
  min-width: 100px;
}

.pagination-btn:hover:not(:disabled) {
  background: #667eea;
  color: white;
  transform: translateY(-1px);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.page-numbers {
  display: flex;
  gap: 5px;
  align-items: center;
  flex-wrap: wrap;
}

.page-btn {
  background: white;
  border: 2px solid #e9ecef;
  color: #495057;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  min-width: 40px;
  transition: all 0.3s;
}

.page-btn:hover {
  border-color: #667eea;
  color: #667eea;
  transform: translateY(-1px);
}

.page-btn.active {
  background: #667eea;
  border-color: #667eea;
  color: white;
  font-weight: 600;
}

.page-btn:disabled {
  background: transparent;
  border: none;
  color: #999;
  cursor: default;
  transform: none;
}

@media (max-width: 768px) {
  .pagination-controls {
    flex-direction: column;
    gap: 15px;
  }
  
  .page-numbers {
    order: -1;
  }
  
  .pagination-btn {
    min-width: 120px;
  }
}
</style>
