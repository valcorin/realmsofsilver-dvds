<script setup>
import { ref, onMounted } from 'vue';
import DvdTable from './components/DvdTable.vue';
import DvdForm from './components/DvdForm.vue';
import dvdApi from './services/dvdApi.js';

const dvds = ref([]);
const selectedDvd = ref(null);
const editMode = ref(false);
const loading = ref(true);
const error = ref(null);

// Load DVDs from the API when component mounts
onMounted(async () => {
  await loadDvds();
});

const loadDvds = async () => {
  try {
    loading.value = true;
    error.value = null;
    const data = await dvdApi.fetchDvds();
    dvds.value = data;
  } catch (err) {
    error.value = `Failed to load DVDs: ${err.message}`;
    console.error('Error loading DVDs:', err);
  } finally {
    loading.value = false;
  }
};

const handleSelectDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = false;
};

const handleEditDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = true;
};

const handleUpdateDvd = async (updatedDvd) => {
  try {
    const result = await dvdApi.updateDvd(updatedDvd);
    
    // Update local data
    const index = dvds.value.findIndex(d => d.id === updatedDvd.id);
    if (index !== -1) {
      dvds.value[index] = result;
    }
    
    selectedDvd.value = result;
    editMode.value = false;
  } catch (err) {
    error.value = `Failed to update DVD: ${err.message}`;
    console.error('Error updating DVD:', err);
  }
};

const handleClose = () => {
  selectedDvd.value = null;
  editMode.value = false;
};

const retryLoad = () => {
  loadDvds();
};
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <h1>🎬 Realms of Silver DVD Collection</h1>
      <p class="subtitle">Manage and browse your DVD collection</p>
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
        <DvdTable 
          :dvds="dvds"
          @select-dvd="handleSelectDvd"
          @edit-dvd="handleEditDvd"
        />
        
        <DvdForm 
          v-if="selectedDvd"
          :dvd="selectedDvd"
          :edit-mode="editMode"
          @update-dvd="handleUpdateDvd"
          @close="handleClose"
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
  padding: 40px 20px;
  color: white;
}

.app-header h1 {
  margin: 0;
  font-size: 2.5em;
  font-weight: 700;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
}

.subtitle {
  margin: 10px 0 0;
  font-size: 1.2em;
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
</style>
