<template>
  <div class="modal-overlay" v-if="dvd" @click="close">
    <div class="modal-container" @click.stop>
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
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
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

// Handle escape key to close modal
const handleEscape = (event) => {
  if (event.key === 'Escape') {
    close();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleEscape);
  // Prevent body scroll when modal is open
  document.body.style.overflow = 'hidden';
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
  // Restore body scroll
  document.body.style.overflow = '';
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
  // Restore body scroll when closing
  document.body.style.overflow = '';
  emit('close');
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  padding: 20px;
  box-sizing: border-box;
}

.modal-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 0;
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 25px 15px 25px;
  border-bottom: 2px solid #667eea;
  background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
  border-radius: 12px 12px 0 0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.form-header h2 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.5em;
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
  padding: 5px;
  width: 35px;
  height: 35px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.3s;
}

.btn-close:hover {
  background-color: rgba(255, 255, 255, 0.8);
  color: #333;
  transform: scale(1.1);
}

.form-content {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 25px;
  align-items: start;
  padding: 25px;
}

.dvd-image-section {
  min-width: 200px;
}

.dvd-cover {
  width: 200px;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  object-fit: cover;
}

.dvd-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 6px;
  color: #555;
  font-size: 0.9em;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px;
  border: 2px solid #e1e5e9;
  border-radius: 6px;
  font-size: 1em;
  transition: all 0.3s;
  background-color: #fff;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group input:disabled,
.form-group select:disabled,
.form-group textarea:disabled {
  background-color: #f8f9fa;
  border-color: #e9ecef;
  cursor: not-allowed;
}

.form-group textarea {
  resize: vertical;
  font-family: inherit;
  min-height: 80px;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 15px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #e9ecef;
}

.btn-primary,
.btn-secondary {
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1em;
  font-weight: 600;
  transition: all 0.3s;
  min-width: 100px;
}

.btn-primary {
  background-color: #667eea;
  color: white;
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  background-color: #5568d3;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background-color: #f8f9fa;
  color: #495057;
  border: 2px solid #e9ecef;
}

.btn-secondary:hover {
  background-color: #e9ecef;
  border-color: #dee2e6;
  transform: translateY(-1px);
}

@media (max-width: 768px) {
  .modal-overlay {
    padding: 10px;
  }
  
  .modal-container {
    max-height: 95vh;
  }
  
  .form-content {
    grid-template-columns: 1fr;
    gap: 20px;
    padding: 20px;
  }
  
  .form-row {
    grid-template-columns: 1fr;
    gap: 15px;
  }
  
  .dvd-cover {
    width: 150px;
    margin: 0 auto;
    display: block;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn-primary,
  .btn-secondary {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .form-header {
    padding: 15px 20px 10px 20px;
  }
  
  .form-header h2 {
    font-size: 1.3em;
  }
  
  .form-content {
    padding: 15px;
  }
}
</style>
