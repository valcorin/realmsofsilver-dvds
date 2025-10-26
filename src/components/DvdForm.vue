<template>
  <div class="dvd-form-container" v-if="dvd">
    <div class="form-header">
      <h2>{{ isEditing ? 'Edit DVD' : 'DVD Details' }}</h2>
      <button @click="close" class="btn-close">✕</button>
    </div>
    
    <div class="form-content">
      <!-- DVD Cover Image -->
      <div v-if="dvdImageUrl" class="dvd-image-section">
        <img :src="dvdImageUrl" :alt="formData.title" class="dvd-cover" />
      </div>
      
      <form @submit.prevent="save" class="dvd-form">
      <div class="form-group">
        <label for="title">Title:</label>
        <input 
          v-model="formData.title" 
          id="title" 
          type="text" 
          :disabled="!isEditing"
          required
        />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="year">Year:</label>
          <input 
            v-model.number="formData.year" 
            id="year" 
            type="number" 
            :disabled="!isEditing"
            required
          />
        </div>

        <div class="form-group">
          <label for="rating">Rating:</label>
          <input 
            v-model="formData.rating" 
            id="rating" 
            type="text" 
            :disabled="!isEditing"
          />
        </div>
      </div>

      <div class="form-group">
        <label for="director">Director:</label>
        <input 
          v-model="formData.director" 
          id="director" 
          type="text" 
          :disabled="!isEditing"
          required
        />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="genre">Genre:</label>
          <input 
            v-model="formData.genre" 
            id="genre" 
            type="text" 
            :disabled="!isEditing"
          />
        </div>

        <div class="form-group">
          <label for="runtime">Runtime:</label>
          <input 
            v-model="formData.runtime" 
            id="runtime" 
            type="text" 
            :disabled="!isEditing"
          />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="format">Format:</label>
          <select 
            v-model="formData.format" 
            id="format" 
            :disabled="!isEditing"
          >
            <option value="DVD">DVD</option>
            <option value="Blu-ray">Blu-ray</option>
            <option value="4K UHD">4K UHD</option>
            <option value="Digital">Digital</option>
          </select>
        </div>

        <div class="form-group">
          <label for="condition">Condition:</label>
          <select 
            v-model="formData.condition" 
            id="condition" 
            :disabled="!isEditing"
          >
            <option value="Excellent">Excellent</option>
            <option value="Very Good">Very Good</option>
            <option value="Good">Good</option>
            <option value="Fair">Fair</option>
            <option value="Poor">Poor</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes:</label>
        <textarea 
          v-model="formData.notes" 
          id="notes" 
          rows="3"
          :disabled="!isEditing"
        ></textarea>
      </div>

      <div class="form-actions">
        <button 
          v-if="!isEditing" 
          @click="startEdit" 
          type="button" 
          class="btn-primary"
        >
          Edit
        </button>
        <template v-else>
          <button type="submit" class="btn-primary">Save</button>
          <button @click="cancel" type="button" class="btn-secondary">Cancel</button>
        </template>
        <button @click="close" type="button" class="btn-secondary">Close</button>
      </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import dvdApi from '../services/dvdApi.js';

const props = defineProps({
  dvd: {
    type: Object,
    default: null
  },
  editMode: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update-dvd', 'close']);

const isEditing = ref(props.editMode);
const formData = ref({ ...props.dvd });

// Computed property for DVD image URL
const dvdImageUrl = computed(() => {
  if (props.dvd && props.dvd.image) {
    return dvdApi.getImageUrl(props.dvd.image);
  }
  return null;
});

watch(() => props.dvd, (newDvd) => {
  if (newDvd) {
    formData.value = { ...newDvd };
    isEditing.value = props.editMode;
  }
});

watch(() => props.editMode, (newMode) => {
  isEditing.value = newMode;
});

const startEdit = () => {
  isEditing.value = true;
};

const save = () => {
  emit('update-dvd', { ...formData.value });
  isEditing.value = false;
};

const cancel = () => {
  formData.value = { ...props.dvd };
  isEditing.value = false;
};

const close = () => {
  emit('close');
};
</script>

<style scoped>
.dvd-form-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  padding: 20px;
  max-width: 800px;
  margin: 20px auto;
}

.form-content {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 20px;
  align-items: start;
}

.dvd-image-section {
  min-width: 200px;
}

.dvd-cover {
  width: 200px;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  object-fit: cover;
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #667eea;
}

.form-header h2 {
  margin: 0;
  color: #2c3e50;
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.3s;
}

.btn-close:hover {
  background-color: #f5f5f5;
  color: #333;
}

.dvd-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 5px;
  color: #555;
  font-size: 0.9em;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1em;
  transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.form-group input:disabled,
.form-group select:disabled,
.form-group textarea:disabled {
  background-color: #f9f9f9;
  cursor: not-allowed;
}

.form-group textarea {
  resize: vertical;
  font-family: inherit;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 10px;
  justify-content: flex-end;
}

.btn-primary,
.btn-secondary {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1em;
  transition: all 0.3s;
}

.btn-primary {
  background-color: #667eea;
  color: white;
}

.btn-primary:hover {
  background-color: #5568d3;
}

.btn-secondary {
  background-color: #e0e0e0;
  color: #333;
}

.btn-secondary:hover {
  background-color: #d0d0d0;
}

@media (max-width: 600px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-content {
    grid-template-columns: 1fr;
  }
  
  .dvd-cover {
    width: 150px;
    margin: 0 auto;
    display: block;
  }
}
</style>
