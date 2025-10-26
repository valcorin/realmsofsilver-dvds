<template>
  <div class="modal-overlay" v-if="dvd" @click="close">
    <div class="modal-container" @click.stop>
      <div class="form-header">
        <h2>{{ isEditing ? 'Edit DVD' : 'DVD Details' }}</h2>
        <button @click="close" class="btn-close">✕</button>
      </div>
      
      <div class="form-content">
        <!-- DVD Cover Image and upload -->
        <div class="dvd-image-section">
          <!-- show preview of newly selected file first, otherwise existing image -->
          <img v-if="previewUrl" :src="previewUrl" :alt="formData.title" class="dvd-cover" />
          <img v-else-if="dvdImageUrl" :src="dvdImageUrl" :alt="formData.title" class="dvd-cover" />

          <!-- File input only available when editing -->
          <div v-if="isEditing" style="margin-top:12px; display:flex; gap:8px; flex-direction:column;">
            <input type="file" accept="image/*" @change="onFileChange" />
            <div style="display:flex; gap:8px; align-items:center;">
              <button v-if="(formData.image || previewUrl)" @click.prevent="removeImage" class="btn-secondary">Remove image</button>
              <small v-if="formData.image && !previewUrl">Current: {{ formData.image.name || 'existing image' }}</small>
            </div>
          </div>
        </div>
        
        <form @submit.prevent="save" class="dvd-form">
        <div class="form-group">
          <label for="title">Title:</label>
          <div class="title-row">
            <input 
              ref="titleInput"
              v-model="formData.title" 
              id="title" 
              type="text" 
              :disabled="!isEditing"
              required
            />
            <button
              type="button"
              class="btn-fetch"
              :disabled="!isEditing || !formData.title || wikiLoading"
              @click="fetchFromWikipedia"
              title="Fetch details from Wikipedia"
            >
              <span v-if="!wikiLoading">Fetch</span>
              <span v-else>Loading…</span>
            </button>
          </div>
          <div class="fetch-error" v-if="wikiError">{{ wikiError }}</div>
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
          <label for="directors">Directors:</label>
          <div class="actor-input" :class="{ disabled: !isEditing }" @click="focusDirectorInput">
            <template v-for="(dir, idx) in directorsArray" :key="idx">
              <span v-if="directorEditIndex !== idx" class="actor-token" @click.stop="startEditDirector(idx)">
                {{ dir }}
                <button v-if="isEditing" type="button" class="token-remove" @click.stop="removeDirector(idx)">✕</button>
              </span>
              <input
                v-else
                ref="directorEditInput"
                class="token-edit-input"
                v-model="directorEditValue"
                @keydown="onDirectorEditKeydown"
                @blur="commitDirectorEdit"
              />
            </template>
            <input
              ref="directorInput"
              v-show="isEditing && directorEditIndex === -1"
              v-model="directorInputValue"
              @keydown="onDirectorKeydown"
              @blur="onDirectorBlur"
              placeholder="Add director and press Enter or comma"
            />
            <div v-if="!isEditing && directorsArray.length === 0" class="hint">No director listed</div>
          </div>
        </div>

        <div class="form-group">
          <label for="actors">Actors:</label>
          <div class="actor-input" :class="{ disabled: !isEditing }" @click="focusActorInput">
            <template v-for="(actor, idx) in actorsArray" :key="idx">
              <span v-if="actorEditIndex !== idx" class="actor-token" @click.stop="startEditActor(idx)">
                {{ actor }}
                <button v-if="isEditing" type="button" class="token-remove" @click.stop="removeActor(idx)">✕</button>
              </span>
              <input
                v-else
                ref="actorEditInput"
                class="token-edit-input"
                v-model="actorEditValue"
                @keydown="onActorEditKeydown"
                @blur="commitActorEdit"
              />
            </template>
            <input
              ref="actorInput"
              v-show="isEditing && actorEditIndex === -1"
              v-model="actorInputValue"
              @keydown="onActorKeydown"
              @blur="onActorBlur"
              placeholder="Add actor and press Enter or comma"
            />
            <div v-if="!isEditing && actorsArray.length === 0" class="hint">No actors listed</div>
          </div>
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
          <button v-if="!isNew" @click="close" type="button" class="btn-secondary">Close</button>
        </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
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
const isNew = computed(() => {
  return !props.dvd || (!props.dvd.id && !props.dvd.dkey);
});
// File handling for image upload
const selectedFile = ref(null);
const previewUrl = ref(null);
const removeFlag = ref(false); // when true, indicates the image should be removed
// Actors token input state
const actorsArray = ref([]);
const actorInputValue = ref('');
const actorInput = ref(null);
// editing state for an actor token (index and temp value)
const actorEditIndex = ref(-1);
const actorEditValue = ref('');
const actorEditInput = ref(null);

// Directors token input state (multiple directors)
const directorsArray = ref([]);
const directorInputValue = ref('');
const directorInput = ref(null);
// editing state for a director token
const directorEditIndex = ref(-1);
const directorEditValue = ref('');
const directorEditInput = ref(null);

// refs for focusing
const titleInput = ref(null);

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

// Wikipedia fetch state
const wikiLoading = ref(false);
const wikiError = ref(null);

const fetchFromWikipedia = async () => {
  const title = (formData.value.title || '').trim();
  if (!title) return;
  wikiLoading.value = true;
  wikiError.value = null;
  try {
    // 1) search for the page
    const searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' + encodeURIComponent(title) + '&format=json&utf8=1&srlimit=1&origin=*';
    const sr = await fetch(searchUrl).then(r => r.json());
    const hit = sr?.query?.search?.[0];
    if (!hit) {
      wikiError.value = 'No Wikipedia page found for this title.';
      return;
    }
    const pageTitle = hit.title;

    // 2) get wikitext revision
    const revUrl = 'https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&rvslots=*&titles=' + encodeURIComponent(pageTitle) + '&format=json&utf8=1&origin=*';
    const revRes = await fetch(revUrl).then(r => r.json());
    const pages = revRes?.query?.pages || {};
    const page = Object.values(pages)[0] || {};
    let content = '';
    try {
      const rev = page.revisions?.[0];
      if (rev) {
        const slots = rev.slots || {};
        const slot = Object.values(slots)[0] || {};
        content = slot['*'] || slot['content'] || '';
      }
    } catch (e) {
      content = '';
    }

    // helper to extract infobox fields
    const extractInfobox = (keyRegex) => {
      if (!content) return null;
      const m = content.match(new RegExp('^\\|\\s*(' + keyRegex + ')\\s*=\\s*(.+)$', 'im'));
      if (!m) return null;
      return cleanWikiText(m[2].trim());
    };

    // directors
    const dirRaw = extractInfobox('director|directed by|directors');
    if (dirRaw) {
      const parts = dirRaw.split(',').map(s => s.trim()).filter(Boolean);
      directorsArray.value = parts;
      formData.value.directors = parts.join(', ');
      formData.value.director = parts.join(', ');
    }

    // actors / starring
    const starsRaw = extractInfobox('starring|cast|stars');
    if (starsRaw) {
      let parts = [];
      // Handle Plainlist template and pipe-separated lists
      if (/\{\{\s*Plainlist\b/i.test(starsRaw)) {
        // extract inside of Plainlist and split on pipes/newlines/bullets
        const m = starsRaw.match(/\{\{\s*Plainlist\s*\|(.*?)\}\}/is);
        if (m) {
          const inner = m[1];
          parts = inner.split(/[|\n\r]+/).map(s => s.replace(/^\s*\*\s*/, '').trim()).filter(Boolean);
        }
      } else if (starsRaw.indexOf('|') !== -1 && starsRaw.indexOf(',') === -1) {
        parts = starsRaw.split('|').map(s => s.trim()).filter(Boolean);
      } else {
        parts = starsRaw.split(',').map(s => s.trim()).filter(Boolean);
      }
      actorsArray.value = parts;
      formData.value.actors = parts.join(', ');
      formData.value.stars = parts.join(', ');
    }

    // try to extract year from released or from page content/snippet
    let year = null;
    const releasedRaw = extractInfobox('released|release date');
    if (releasedRaw) {
      const m = releasedRaw.match(/(19|20)\\d{2}/);
      if (m) year = m[0];
    }
    if (!year) {
      // fallback: try to parse from hit snippet or title
      const snippet = hit?.snippet || '';
      const m2 = snippet.match(/(19|20)\\d{2}/);
      if (m2) year = m2[0];
    }
    if (!year && content) {
      const m3 = content.match(/(19|20)\\d{2}/);
      if (m3) year = m3[0];
    }
    if (year) {
      formData.value.year = Number(year);
    }

    // --- extract more fields: genre, runtime, and intro description ---
    const genreRaw = extractInfobox('genre');
    if (genreRaw) {
      // keep as a comma-separated string
      const parts = genreRaw.split(',').map(s => s.trim()).filter(Boolean);
      formData.value.genre = parts.join(', ');
    }

    const runtimeRaw = extractInfobox('running_time|runtime|running time|running_time');
    if (runtimeRaw) {
      // normalize runtime to a readable string (leave as-is but cleaned)
      formData.value.runtime = runtimeRaw;
    }

    // Fetch plain-text extract (intro) to use as notes/description
    try {
      const extractUrl2 = 'https://en.wikipedia.org/w/api.php?action=query&prop=extracts&exintro=1&explaintext=1&titles=' + encodeURIComponent(pageTitle) + '&format=json&utf8=1&origin=*';
      const extResp = await fetch(extractUrl2).then(r => r.json());
      const pages2 = extResp?.query?.pages || {};
      const p2 = Object.values(pages2)[0] || {};
      const extractText = p2.extract || '';
      if (extractText) {
        // take the first paragraph as a short description
        const para = extractText.split('\n\n').map(s => s.trim()).find(Boolean) || extractText.trim();
        // don't overwrite notes if already present unless it was empty
        if (!formData.value.notes || formData.value.notes.trim() === '') {
          formData.value.notes = para;
        }
      }
    } catch (e) {
      // ignore extract failures
    }

  } catch (err) {
    wikiError.value = 'Failed to fetch from Wikipedia.';
    console.error('Wikipedia fetch error', err);
  } finally {
    wikiLoading.value = false;
  }
};

function cleanWikiText(s) {
  if (!s) return '';
  // remove templates {{...}} (simple)
  s = s.replace(/\{\{[^}]*\}\}/g, '');
  // replace links [[a|b]] or [[b]] -> b
  s = s.replace(/\[\[([^\]]+)\]\]/g, (_, inner) => inner.split('|').pop());
  // remove refs (use [\s\S] to avoid depending on the `s` flag)
  s = s.replace(/<ref[^>]*>[\s\S]*?<\/ref>/gi, '');
  s = s.replace(/<[^>]+>/g, '');
  s = s.replace(/\s*\n\s*/g, ', ');
  s = s.replace(/\s*,\s*/g, ', ');
  return s.trim();
}

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

// Initialize actors when the prop is available. Use immediate:true so opening the modal
// with an already-loaded `dvd` initializes the tokens immediately.
watch(() => props.dvd, (newDvd) => {
  if (newDvd) {
    formData.value = { ...newDvd };
    isEditing.value = props.editMode;
    // reset file selections when dvd changes
    selectedFile.value = null;
    previewUrl.value = null;
    removeFlag.value = false;
    // initialize actors array from incoming data (comma separated or array)
    if (newDvd.actors && typeof newDvd.actors === 'string') {
      actorsArray.value = newDvd.actors.split(',').map(s => s.trim()).filter(Boolean);
    } else if (Array.isArray(newDvd.actors)) {
      actorsArray.value = newDvd.actors.map(s => String(s).trim()).filter(Boolean);
    } else if (newDvd.stars && String(newDvd.stars).trim() !== '') {
      // fallback to legacy `stars` column if `actors` missing
      actorsArray.value = String(newDvd.stars).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      actorsArray.value = [];
    }
    // initialize directors array from incoming data (comma separated or array)
    if (newDvd.directors && typeof newDvd.directors === 'string') {
      directorsArray.value = newDvd.directors.split(',').map(s => s.trim()).filter(Boolean);
    } else if (Array.isArray(newDvd.directors)) {
      directorsArray.value = newDvd.directors.map(s => String(s).trim()).filter(Boolean);
    } else if (newDvd.director && String(newDvd.director).trim() !== '') {
      // fallback to legacy `director` column if `directors` missing
      directorsArray.value = String(newDvd.director).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      directorsArray.value = [];
    }
    // If this is a newly-created DVD and we're in edit mode, autofocus title
    if (isEditing.value && (!newDvd.id && !newDvd.dkey)) {
      nextTick(() => {
        try { if (titleInput.value) titleInput.value.focus(); } catch (e) {}
      });
    }
  }
}, { immediate: true });

watch(() => props.editMode, (newMode) => {
  isEditing.value = newMode;
});

const startEdit = () => {
  isEditing.value = true;
};

const save = () => {
  // convert selected file to base64 (if any) and attach
  const performSave = async () => {
    try {
      // sync actors tokens back into formData as comma-separated string
      formData.value.actors = actorsArray.value.join(', ');
        // sync directors tokens back into formData as comma-separated string
        formData.value.directors = directorsArray.value.join(', ');
        // also set legacy `director` field for server compatibility
        formData.value.director = directorsArray.value.join(', ');

      if (selectedFile.value) {
        const base64 = await fileToBase64(selectedFile.value);
        // store image object expected by API: { name, type, data }
        const mime = selectedFile.value.type || '';
        const subtype = mime.includes('/') ? mime.split('/')[1] : mime || 'png';
        formData.value.image = {
          name: selectedFile.value.name || '',
          type: subtype,
          data: base64
        };
      } else if (removeFlag.value) {
        // explicit request to remove image
        formData.value.image = null;
      }

      emit('update-dvd', { ...formData.value });
      isEditing.value = false;
    } catch (err) {
      console.error('Error preparing image for upload:', err);
      // still emit without image if conversion fails
        emit('update-dvd', { ...formData.value });
      isEditing.value = false;
    }
  };

  performSave();
};

const cancel = () => {
  // If this is a new DVD (no id/dkey), cancelling should close the modal
  if (!props.dvd || (!props.dvd.id && !props.dvd.dkey)) {
    close();
    return;
  }

  // Otherwise revert changes and switch back to view mode
  formData.value = { ...props.dvd };
  isEditing.value = false;
  // clear any selected file/preview when cancelling
  selectedFile.value = null;
  previewUrl.value = null;
  removeFlag.value = false;
  // reset directors and actors arrays back to original
  if (props.dvd) {
    if (props.dvd.directors && typeof props.dvd.directors === 'string') {
      directorsArray.value = props.dvd.directors.split(',').map(s => s.trim()).filter(Boolean);
    } else if (Array.isArray(props.dvd.directors)) {
      directorsArray.value = props.dvd.directors.map(s => String(s).trim()).filter(Boolean);
    } else if (props.dvd.director && String(props.dvd.director).trim() !== '') {
      directorsArray.value = String(props.dvd.director).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      directorsArray.value = [];
    }
    if (props.dvd.actors && typeof props.dvd.actors === 'string') {
      actorsArray.value = props.dvd.actors.split(',').map(s => s.trim()).filter(Boolean);
    } else if (Array.isArray(props.dvd.actors)) {
      actorsArray.value = props.dvd.actors.map(s => String(s).trim()).filter(Boolean);
    } else if (props.dvd.stars && String(props.dvd.stars).trim() !== '') {
      actorsArray.value = String(props.dvd.stars).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      actorsArray.value = [];
    }
  }
};

const close = () => {
  // Restore body scroll when closing
  document.body.style.overflow = '';
  emit('close');
};

// Handle file selection and preview
const onFileChange = (event) => {
  const file = event.target.files && event.target.files[0];
  if (!file) return;
  selectedFile.value = file;
  removeFlag.value = false;
  // create object URL for preview
  try {
    previewUrl.value = URL.createObjectURL(file);
  } catch (e) {
    previewUrl.value = null;
  }
};

const removeImage = () => {
  selectedFile.value = null;
  previewUrl.value = null;
  removeFlag.value = true;
  // also clear any existing image metadata in the form so emitted payload shows removal
  formData.value.image = null;
};

// Convert File to base64 string (without data: prefix)
const fileToBase64 = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result;
      if (typeof result === 'string') {
        // result looks like 'data:<mime>;base64,<data>'
        const idx = result.indexOf(',');
        if (idx !== -1) {
          resolve(result.slice(idx + 1));
        } else {
          resolve(result);
        }
      } else {
        reject(new Error('Unexpected FileReader result type'));
      }
    };
    reader.onerror = (err) => reject(err);
    reader.readAsDataURL(file);
  });
};

// --- Actors token input helpers ---
const focusActorInput = () => {
  if (actorInput.value && isEditing.value) actorInput.value.focus();
};

const focusDirectorInput = () => {
  if (directorInput.value && isEditing.value) directorInput.value.focus();
};

const addActor = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean);
  for (const p of parts) {
    if (!actorsArray.value.includes(p)) actorsArray.value.push(p);
  }
  actorInputValue.value = '';
};

const addDirector = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean);
  for (const p of parts) {
    if (!directorsArray.value.includes(p)) directorsArray.value.push(p);
  }
  directorInputValue.value = '';
};

const removeDirector = (idx) => {
  directorsArray.value.splice(idx, 1);
};

const onDirectorKeydown = (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addDirector(directorInputValue.value);
  } else if (e.key === 'Backspace' && (!directorInputValue.value || directorInputValue.value.length === 0)) {
    directorsArray.value.pop();
  }
};

const onDirectorBlur = () => {
  if (directorInputValue.value && directorInputValue.value.trim()) {
    addDirector(directorInputValue.value);
  }
};

const removeActor = (idx) => {
  actorsArray.value.splice(idx, 1);
};

const onActorKeydown = (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addActor(actorInputValue.value);
  } else if (e.key === 'Backspace' && (!actorInputValue.value || actorInputValue.value.length === 0)) {
    // remove last token
    actorsArray.value.pop();
  }
};

const onActorBlur = () => {
  if (actorInputValue.value && actorInputValue.value.trim()) {
    addActor(actorInputValue.value);
  }
};

// --- Token edit handlers for actors/directors ---
const startEditActor = (idx) => {
  if (!isEditing.value) return;
  actorEditIndex.value = idx;
  actorEditValue.value = actorsArray.value[idx] || '';
  nextTick(() => {
    if (actorEditInput.value) actorEditInput.value.focus();
  });
};

const commitActorEdit = () => {
  const idx = actorEditIndex.value;
  if (idx < 0) return;
  const v = (actorEditValue.value || '').trim();
  if (v === '') {
    // remove token if empty
    actorsArray.value.splice(idx, 1);
  } else {
    actorsArray.value.splice(idx, 1, v);
  }
  actorEditIndex.value = -1;
  actorEditValue.value = '';
};

const onActorEditKeydown = (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    commitActorEdit();
  } else if (e.key === 'Escape') {
    // cancel edit
    actorEditIndex.value = -1;
    actorEditValue.value = '';
  }
};

const startEditDirector = (idx) => {
  if (!isEditing.value) return;
  directorEditIndex.value = idx;
  directorEditValue.value = directorsArray.value[idx] || '';
  nextTick(() => {
    if (directorEditInput.value) directorEditInput.value.focus();
  });
};

const commitDirectorEdit = () => {
  const idx = directorEditIndex.value;
  if (idx < 0) return;
  const v = (directorEditValue.value || '').trim();
  if (v === '') {
    directorsArray.value.splice(idx, 1);
  } else {
    directorsArray.value.splice(idx, 1, v);
  }
  directorEditIndex.value = -1;
  directorEditValue.value = '';
};

const onDirectorEditKeydown = (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    commitDirectorEdit();
  } else if (e.key === 'Escape') {
    directorEditIndex.value = -1;
    directorEditValue.value = '';
  }
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

.title-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.title-row input {
  flex: 1 1 auto;
  min-width: 0; /* allow flexing in constrained containers */
}

.btn-fetch {
  margin-left: 6px;
  padding: 10px 14px;
  border-radius: 6px;
  border: none;
  background: #48bb78;
  color: white;
  font-weight: 600;
  cursor: pointer;
}

.btn-fetch[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
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
<style scoped>
.actor-input {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border: 2px solid #e1e5e9;
  border-radius: 6px;
  min-height: 44px;
  background: #fff;
}
.actor-input.disabled {
  background: #f8f9fa;
}
.actor-token {
  background: #eef2ff;
  color: #2c3e50;
  padding: 6px 8px;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.9em;
}
.actor-token .token-remove {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 0.85em;
  color: #4a5568;
}
.actor-input input {
  border: none;
  outline: none;
  min-width: 120px;
  font-size: 1em;
}
</style>
