<script setup>
import { ref } from 'vue';
import DvdTable from './components/DvdTable.vue';
import DvdForm from './components/DvdForm.vue';
import { dvds as initialDvds } from './data/dvds.js';

const dvds = ref([...initialDvds]);
const selectedDvd = ref(null);
const editMode = ref(false);

const handleSelectDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = false;
};

const handleEditDvd = (dvd) => {
  selectedDvd.value = dvd;
  editMode.value = true;
};

const handleUpdateDvd = (updatedDvd) => {
  const index = dvds.value.findIndex(d => d.id === updatedDvd.id);
  if (index !== -1) {
    dvds.value[index] = updatedDvd;
  }
  selectedDvd.value = updatedDvd;
  editMode.value = false;
};

const handleClose = () => {
  selectedDvd.value = null;
  editMode.value = false;
};
</script>

<template>
  <div class="app-container">
    <header class="app-header">
      <h1>🎬 Realms of Silver DVD Collection</h1>
      <p class="subtitle">Manage and browse your DVD collection</p>
    </header>

    <main class="app-main">
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
</style>
