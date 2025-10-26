<template>
  <div class="modal-overlay" v-if="dvd" @click="close">
    <div class="modal-container" @click.stop>
      <div class="form-header">
        <h2>{{ isEditing ? 'Edit DVD' : 'DVD Details' }}</h2>
        <button @click="close" class="btn-close">✕</button>
      </div>
      
      <div class="form-content">
  <form @submit.prevent="save" class="dvd-form">
  <!-- DVD Cover Image and upload (moved inside the form so fields can span under it) -->
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
        <div class="right-column">
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
              <span v-if="wikidataUsed" class="wikidata-badge" title="Some fields were populated from Wikidata">Data from Wikidata</span>
              <div class="fetch-image-status" v-if="fetchImageStatus">{{ fetchImageStatus }}</div>
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

        <div class="form-row">
          <div class="form-group">
            <label for="genre">Genre:</label>
            <div class="actor-input" :class="{ disabled: !isEditing }" @click="focusGenreInput">
              <template v-for="(g, idx) in genresArray" :key="idx">
                <span v-if="genreEditIndex !== idx" class="actor-token" @click.stop="startEditGenre(idx)">
                  {{ g }}
                  <button v-if="isEditing" type="button" class="token-remove" @click.stop="removeGenre(idx)">✕</button>
                </span>
                <input
                  v-else
                  ref="genreEditInput"
                  class="token-edit-input"
                  v-model="genreEditValue"
                  @keydown="onGenreEditKeydown"
                  @blur="commitGenreEdit"
                />
              </template>
              <input
                ref="genreInput"
                v-show="isEditing && genreEditIndex === -1"
                v-model="genreInputValue"
                @keydown="onGenreKeydown"
                @blur="onGenreBlur"
                placeholder="Add genre and press Enter or comma"
              />
              <div v-if="!isEditing && genresArray.length === 0" class="hint">No genre listed</div>
            </div>
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
              <option value="BLU">Blu-ray</option>
              <option value="4K">4K UHD</option>
              <option value="DIG">Digital</option>
              <option value="BCK">Backup</option>
              <option value="VHS">VHS</option>
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

        </div> <!-- .right-column -->

        <div class="form-group full-width">
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

        <div class="form-group full-width">
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

        <div class="form-group full-width">
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
            <!-- Delete button left-aligned in the action row -->
            <button v-if="!isNew" type="button" class="btn-delete" @click.prevent="confirmAndDelete">Delete</button>
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

// Genres token input state
const genresArray = ref([]);
const genreInputValue = ref('');
const genreInput = ref(null);
// editing state for a genre token
const genreEditIndex = ref(-1);
const genreEditValue = ref('');
const genreEditInput = ref(null);

// refs for focusing
const titleInput = ref(null);

// Computed property for DVD image URL
const dvdImageUrl = computed(() => {
  if (props.dvd && props.dvd.image) {
    return dvdApi.getImageUrl(props.dvd.image);
  }
  return null;
});

// Clean genre terms: drop the word "film" or "films" if present and trim
function cleanGenreTerm(s) {
  if (!s) return '';
  let t = String(s).trim();
  // remove trailing/standalone 'film' or 'films'
  t = t.replace(/\bfilms?\b/ig, '');
  // collapse whitespace and separators
  t = t.replace(/[\s\-_]+/g, ' ').trim();
  // remove stray commas
  t = t.replace(/^,|,$/g, '').trim();
  return t;
}

// Handle escape key to close modal
const handleEscape = (event) => {
  if (event.key === 'Escape') {
    close();
  }
};

// Wikipedia fetch state
const wikiLoading = ref(false);
const wikiError = ref(null);
const wikidataUsed = ref(false);
const fetchImageStatus = ref('');
// Controller to cancel in-flight wiki/wikidata requests
let wikiController = null;

const fetchFromWikipedia = async () => {
  const title = (formData.value.title || '').trim();
  if (!title) return;
  // abort any previous fetch in progress
  if (wikiController) {
    try { wikiController.abort(); } catch (e) {}
    wikiController = null;
  }
  wikiController = new AbortController();
  const signal = wikiController.signal;

  wikiLoading.value = true;
  wikiError.value = null;
  fetchImageStatus.value = '';
  try {
    // 1) search for the page
  const searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' + encodeURIComponent(title) + '&format=json&utf8=1&srlimit=1&origin=*';
  const sr = await fetch(searchUrl, { signal }).then(r => r.json());
    const hit = sr?.query?.search?.[0];
    if (!hit) {
      wikiError.value = 'No Wikipedia page found for this title.';
      return;
    }
    const pageTitle = hit.title;
  // update the form title to the canonical Wikipedia page title (fix case/spacing)
  formData.value.title = pageTitle;

      // Additionally, check whether this page transcludes Template:Infobox_film.
      // Some pages use nested templates where the starring list is harder to extract from revisions; if
      // the page transcludes the infobox, request the parsed wikitext via action=parse and prefer that
      // when extracting infobox fields.
      try {
        const embedUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=embeddedin&eititle=Template:Infobox_film&einamespace=0&format=json&eilimit=500&origin=*';
        const embedResp = await fetch(embedUrl, { signal }).then(r => r.json());
        const embedded = embedResp?.query?.embeddedin || [];
        const embedsTitles = embedded.map(e => e.title);
        const transcludesInfobox = embedsTitles.includes(pageTitle);
        if (transcludesInfobox) {
          try {
            const parseUrl = 'https://en.wikipedia.org/w/api.php?action=parse&page=' + encodeURIComponent(pageTitle) + '&prop=wikitext&format=json&origin=*';
            const parseResp = await fetch(parseUrl, { signal }).then(r => r.json());
            const parsedWikitext = parseResp?.parse?.wikitext?.['*'] || '';
            if (parsedWikitext) {
              // prefer parsed wikitext as the content to extract from
              content = parsedWikitext;
            }
          } catch (e) {
            if (e && e.name === 'AbortError') return;
            console.debug('Failed to fetch parsed wikitext for page', pageTitle, e);
          }
        }
      } catch (e) {
        // ignore failures for the embeddedin check
      }

      // Try Wikidata first (structured data) by resolving the Wikidata Q-id from the Wikipedia page
      let wikibaseItem = null;
      try {
        const propsUrl = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(pageTitle) + '&prop=pageprops&format=json&origin=*';
        const propsResp = await fetch(propsUrl, { signal }).then(r => r.json());
        const pagesProps = propsResp?.query?.pages || {};
        const pageObj = Object.values(pagesProps)[0] || {};
        wikibaseItem = pageObj.pageprops && pageObj.pageprops.wikibase_item;
        if (wikibaseItem) {
          // Query Wikidata SPARQL endpoint for structured fields
          // Require that the Wikidata item is an instance of a film (P31 = Q11424)
          const sparql = `SELECT ?directorLabel ?castLabel ?genreLabel ?duration ?publicationDate WHERE {
            wd:${wikibaseItem} wdt:P31 wd:Q11424 .
            OPTIONAL { wd:${wikibaseItem} wdt:P57 ?director. }
            OPTIONAL { wd:${wikibaseItem} wdt:P161 ?cast. }
            OPTIONAL { wd:${wikibaseItem} wdt:P136 ?genre. }
            OPTIONAL { wd:${wikibaseItem} wdt:P2047 ?duration. }
            OPTIONAL { wd:${wikibaseItem} wdt:P577 ?publicationDate. }
            SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
          }`;

          const sparqlUrl = 'https://query.wikidata.org/sparql?format=json&query=' + encodeURIComponent(sparql);
          const wdResp = await fetch(sparqlUrl, { headers: { 'Accept': 'application/sparql-results+json' }, signal }).then(r => r.json());
          const rows = wdResp?.results?.bindings || [];

          // Collect lists in the order they appear in the SPARQL results, dedup case-insensitively
          const directorList = [];
          const castList = [];
          const genreList = [];
          let durationVal = null;
          let pubDateVal = null;

          const pick = (row, keys) => {
            for (const k of keys) {
              if (row[k] && row[k].value) return row[k].value;
            }
            return null;
          };

          rows.forEach(row => {
            const d = pick(row, ['directorLabel', 'director']);
            const c = pick(row, ['castLabel', 'cast']);
            const g = pick(row, ['genreLabel', 'genre']);
            const dur = pick(row, ['duration']);
            const pub = pick(row, ['publicationDate']);

            if (d) {
              const kd = String(d).trim();
              if (kd && !directorList.some(x => x.toLowerCase() === kd.toLowerCase())) directorList.push(kd);
            }
            if (c) {
              const kc = String(c).trim();
              if (kc && !castList.some(x => x.toLowerCase() === kc.toLowerCase())) castList.push(kc);
            }
            if (g) {
              const kg = String(g).trim();
              if (kg && !genreList.some(x => x.toLowerCase() === kg.toLowerCase())) genreList.push(kg);
            }
            if (!durationVal && dur) durationVal = dur;
            if (!pubDateVal && pub) pubDateVal = pub;
          });

          if (directorList.length > 0) {
            directorsArray.value = directorList;
            formData.value.directors = directorList.join(', ');
            formData.value.director = directorList.join(', ');
          }

          if (castList.length > 0) {
            actorsArray.value = castList;
            formData.value.actors = castList.join(', ');
          }

          if (genreList.length > 0) {
            const raw = genreList.map(s => cleanGenreTerm(s)).filter(Boolean);
            const seen = new Set();
            const parts = [];
            for (const x of raw) {
              const key = x.toLowerCase();
              if (!seen.has(key)) {
                seen.add(key);
                parts.push(x);
              }
            }
            genresArray.value = parts;
            formData.value.genre = parts.join(', ');
          }

          if (durationVal) {
            const minutes = parseIsoDurationToMinutes(durationVal);
            if (minutes !== null) formData.value.runtime = String(minutes);
            else formData.value.runtime = String(durationVal);
          }

          if (pubDateVal) {
            try {
              const y = new Date(pubDateVal).getFullYear();
              if (!Number.isNaN(y)) formData.value.year = Number(y);
            } catch (e) {}
          }

          if (directorList.length > 0 || castList.length > 0 || genreList.length > 0 || durationVal || pubDateVal) {
            wikidataUsed.value = true;
          }
        }
      } catch (e) {
        // If the fetch was aborted, swallow silently
        if (e && e.name === 'AbortError') return;
        // Non-fatal: if Wikidata query fails, fall back to wikitext parsing below
        console.debug('Wikidata lookup failed', e);
      }

      // Attempt to fetch a poster image for the page. Strategy:
      // 1) Try Wikipedia PageImages API (original/thumbnail)
      // 2) Fall back to Wikidata P18 via entity JSON -> resolve to Commons Special:FilePath
      // If an image is found, download it, convert to base64, set previewUrl and formData.image,
      // and append a short attribution to notes. Use the existing `wikiController` signal for cancellation.
      const arrayBufferToBase64 = (buffer) => {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const chunkSize = 0x8000;
        for (let i = 0; i < bytes.length; i += chunkSize) {
          binary += String.fromCharCode.apply(null, Array.prototype.slice.call(bytes, i, i + chunkSize));
        }
        return btoa(binary);
      };

      try {
        let imageUrl = null;
        let imageFilename = null;
        fetchImageStatus.value = 'Searching for cover image…';

        // helper to attempt downloading and assigning an image URL (tries original, thumbnail, then proxy)
        const downloadAndAssign = async (imageUrlParam, imageFilenameParam) => {
          let imageUrlLocal = imageUrlParam;
          let imageFilenameLocal = imageFilenameParam;
          if (!imageUrlLocal) return false;
          console.log('fetchFromWikipedia: resolved imageUrl=', imageUrlLocal, 'imageFilename=', imageFilenameLocal);
          fetchImageStatus.value = 'Downloading cover image…';
          // Download image as binary with a resilient strategy: try original; on failure, try thumbnail variant
          let imgResp = null;
          try {
            imgResp = await fetch(imageUrlLocal, { signal });
            console.debug('fetchFromWikipedia: initial image fetch response', { ok: imgResp.ok, status: imgResp.status, headers: { 'content-type': imgResp.headers.get('Content-Type') } });
          } catch (e) {
            console.debug('fetchFromWikipedia: initial image fetch failed', e);
            imgResp = null;
          }

          // If initial response not ok, try constructing a commons thumbnail URL when possible
          if (!(imgResp && imgResp.ok)) {
            // Construct thumbnail URL by inserting '/thumb' and appending '/250px-<filename>' when possible
            try {
              if (!imageFilenameLocal && imageUrlLocal) {
                // try to derive filename from URL
                const parts = imageUrlLocal.split('/');
                imageFilenameLocal = parts[parts.length - 1] || imageFilenameLocal;
              }
              if (imageUrlLocal && imageFilenameLocal && imageUrlLocal.includes('/wikipedia/')) {
                // split after '/wikipedia/<lang>/'
                const idx = imageUrlLocal.indexOf('/wikipedia/');
                const after = imageUrlLocal.slice(idx + '/wikipedia/'.length); // e.g. 'en/1/1f/Name.jpg'
                const langAndRest = after.split('/');
                const lang = langAndRest.shift();
                const rest = langAndRest.join('/');
                const thumbUrl = 'https://upload.wikimedia.org/wikipedia/' + lang + '/thumb/' + rest + '/250px-' + encodeURIComponent(imageFilenameLocal);
                console.debug('fetchFromWikipedia: trying thumbnail URL', thumbUrl);
                try {
                  imgResp = await fetch(thumbUrl, { signal });
                  console.debug('fetchFromWikipedia: thumbnail fetch response', { ok: imgResp && imgResp.ok, status: imgResp && imgResp.status });
                  if (imgResp && imgResp.ok) {
                    imageUrlLocal = thumbUrl; // use the thumbnail URL moving forward
                  }
                } catch (e) {
                  console.debug('fetchFromWikipedia: thumbnail fetch failed', e);
                }
              }
            } catch (e) {
              console.debug('fetchFromWikipedia: failed to construct thumbnail URL', e);
            }
            // If both original and thumbnail attempts fail, try the server-side proxy to avoid CORS/redirect problems
            try {
              const proxyUrl = '/api/fetch-image.php?url=' + encodeURIComponent(imageUrlLocal);
              console.debug('fetchFromWikipedia: attempting server-side proxy fetch', proxyUrl);
              const proxyResp = await fetch(proxyUrl, { signal }).then(r => r.json());
              console.debug('fetchFromWikipedia: proxy response', proxyResp);
              if (proxyResp && proxyResp.ok && proxyResp.data) {
                const contentType = proxyResp.contentType || '';
                const base64Data = proxyResp.data;
                previewUrl.value = 'data:' + (contentType || 'image/jpeg') + ';base64,' + base64Data;
                const subtype = (contentType && contentType.indexOf('/') !== -1) ? contentType.split('/')[1] : (imageFilenameLocal ? imageFilenameLocal.split('.').pop() : 'jpeg');
                formData.value.image = {
                  name: imageFilenameLocal || ('image.' + subtype),
                  type: subtype,
                  data: base64Data
                };
                fetchImageStatus.value = 'Cover image assigned via proxy';
                console.log('fetchFromWikipedia: assigned formData.image via proxy, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
                // append attribution
                try {
                  const attribution = imageFilenameLocal ? ('Image: ' + imageFilenameLocal + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
                  if (!formData.value.notes || formData.value.notes.trim() === '') {
                    formData.value.notes = attribution;
                  } else if (formData.value.notes.indexOf(attribution) === -1) {
                    formData.value.notes += '\n\n' + attribution;
                  }
                } catch (e) {}
                return true;
              } else {
                console.debug('fetchFromWikipedia: proxy did not return image', proxyResp);
              }
            } catch (e) {
              console.debug('fetchFromWikipedia: proxy fetch failed', e);
            }
          }

          if (imgResp && imgResp.ok) {
            const contentType = imgResp.headers.get('Content-Type') || '';
            const buf = await imgResp.arrayBuffer();
            const base64Data = arrayBufferToBase64(buf);
            console.log('fetchFromWikipedia: downloaded image; contentType=', contentType, 'bytes=', buf.byteLength);
            fetchImageStatus.value = 'Cover image downloaded';
            const subtype = contentType && contentType.indexOf('/') !== -1 ? contentType.split('/')[1] : (imageFilenameLocal ? imageFilenameLocal.split('.').pop() : 'jpeg');
            // set preview and form image (formData.image.data expects raw base64 without data: prefix)
            previewUrl.value = 'data:' + (contentType || ('image/' + subtype)) + ';base64,' + base64Data;
            formData.value.image = {
              name: imageFilenameLocal || ('image.' + subtype),
              type: subtype,
              data: base64Data
            };
            console.log('fetchFromWikipedia: assigned formData.image, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
            fetchImageStatus.value = 'Cover image assigned to form';
            // Append short attribution to notes if not already present
            try {
              const attribution = imageFilenameLocal ? ('Image: ' + imageFilenameLocal + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
              if (!formData.value.notes || formData.value.notes.trim() === '') {
                formData.value.notes = attribution;
              } else if (formData.value.notes.indexOf(attribution) === -1) {
                formData.value.notes += '\n\n' + attribution;
              }
            } catch (e) {
              // ignore attribution failures
            }
            return true;
          }
          console.debug('fetchFromWikipedia: image fetch ultimately failed, imgResp=', imgResp);
          return false;
        };

        // 1) Wikipedia pageimages API (prefer original, else thumbnail)
        try {
          const piUrl = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(pageTitle) + '&prop=pageimages&piprop=original|thumbnail&pithumbsize=600&format=json&origin=*';
          const piResp = await fetch(piUrl, { signal }).then(r => r.json());
          console.debug('fetchFromWikipedia: pageimages response', piResp);
          const piPages = piResp?.query?.pages || {};
          const piPage = Object.values(piPages)[0] || {};
          imageUrl = piPage?.original?.source || piPage?.thumbnail?.source || null;
          if (!imageUrl) {
            fetchImageStatus.value = 'No image on Wikipedia page; checking Wikidata...';
            console.debug('fetchFromWikipedia: no pageimage found for', pageTitle, 'page object:', piPage);
          }
        } catch (e) {
          if (e && e.name === 'AbortError') throw e;
          // ignore pageimages failures
        }

        // 2) Wikidata P18 fallback (if no imageUrl and we have a wikibaseItem)
        if (!imageUrl && wikibaseItem) {
          try {
            const entUrl = 'https://www.wikidata.org/wiki/Special:EntityData/' + encodeURIComponent(wikibaseItem) + '.json';
            const entResp = await fetch(entUrl, { signal }).then(r => r.json());
            console.debug('fetchFromWikipedia: wikidata entity response', entResp);
            const entities = entResp?.entities || {};
            const ent = entities[wikibaseItem] || Object.values(entities)[0] || {};
            const claims = ent.claims || {};
            const p18 = claims.P18 || [];
            if (p18.length > 0) {
              const val = p18[0].mainsnak?.datavalue?.value;
              if (val) {
                // val is usually like 'File:Something.jpg'
                imageFilename = String(val).replace(/^File:/i, '');
                imageUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/' + encodeURIComponent(imageFilename);
                console.debug('fetchFromWikipedia: wikidata P18 found', imageFilename, imageUrl);
              }
            }
          } catch (e) {
            if (e && e.name === 'AbortError') throw e;
            // ignore wikidata P18 failures
          }
        }

        if (!imageUrl && !wikibaseItem) {
          console.debug('fetchFromWikipedia: no wikibaseItem found for page', pageTitle, '— cannot query Wikidata P18');
          fetchImageStatus.value = 'No Wikidata entry for this page; no cover image available';
        }

        if (imageUrl) {
          console.log('fetchFromWikipedia: resolved imageUrl=', imageUrl, 'imageFilename=', imageFilename);
          fetchImageStatus.value = 'Downloading cover image…';
          // Download image as binary with a resilient strategy: try original; on failure, try thumbnail variant
          let imgResp = null;
          try {
            imgResp = await fetch(imageUrl, { signal });
            console.debug('fetchFromWikipedia: initial image fetch response', { ok: imgResp.ok, status: imgResp.status, headers: { 'content-type': imgResp.headers.get('Content-Type') } });
          } catch (e) {
            console.debug('fetchFromWikipedia: initial image fetch failed', e);
            imgResp = null;
          }

          // If initial response not ok, try constructing a commons thumbnail URL when possible
          if (!(imgResp && imgResp.ok)) {
            // Construct thumbnail URL by inserting '/thumb' and appending '/250px-<filename>' when possible
            try {
              if (!imageFilename && imageUrl) {
                // try to derive filename from URL
                const parts = imageUrl.split('/');
                imageFilename = parts[parts.length - 1] || imageFilename;
              }
              if (imageUrl && imageFilename && imageUrl.includes('/wikipedia/')) {
                // split after '/wikipedia/<lang>/'
                const idx = imageUrl.indexOf('/wikipedia/');
                const after = imageUrl.slice(idx + '/wikipedia/'.length); // e.g. 'en/1/1f/Name.jpg'
                const langAndRest = after.split('/');
                const lang = langAndRest.shift();
                const rest = langAndRest.join('/');
                const thumbUrl = 'https://upload.wikimedia.org/wikipedia/' + lang + '/thumb/' + rest + '/250px-' + encodeURIComponent(imageFilename);
                console.debug('fetchFromWikipedia: trying thumbnail URL', thumbUrl);
                  try {
                    imgResp = await fetch(thumbUrl, { signal });
                    console.debug('fetchFromWikipedia: thumbnail fetch response', { ok: imgResp && imgResp.ok, status: imgResp && imgResp.status });
                    if (imgResp && imgResp.ok) {
                      imageUrl = thumbUrl; // use the thumbnail URL moving forward
                    }
                  } catch (e) {
                    console.debug('fetchFromWikipedia: thumbnail fetch failed', e);
                  }
              }
            } catch (e) {
              console.debug('fetchFromWikipedia: failed to construct thumbnail URL', e);
            }
            // If both original and thumbnail attempts fail, try the server-side proxy to avoid CORS/redirect problems
            try {
              const proxyUrl = '/api/fetch-image.php?url=' + encodeURIComponent(imageUrl);
              console.debug('fetchFromWikipedia: attempting server-side proxy fetch', proxyUrl);
              const proxyResp = await fetch(proxyUrl, { signal }).then(r => r.json());
              console.debug('fetchFromWikipedia: proxy response', proxyResp);
              if (proxyResp && proxyResp.ok && proxyResp.data) {
                const contentType = proxyResp.contentType || '';
                const base64Data = proxyResp.data;
                previewUrl.value = 'data:' + (contentType || 'image/jpeg') + ';base64,' + base64Data;
                const subtype = (contentType && contentType.indexOf('/') !== -1) ? contentType.split('/')[1] : (imageFilename ? imageFilename.split('.').pop() : 'jpeg');
                formData.value.image = {
                  name: imageFilename || ('image.' + subtype),
                  type: subtype,
                  data: base64Data
                };
                fetchImageStatus.value = 'Cover image assigned via proxy';
                console.log('fetchFromWikipedia: assigned formData.image via proxy, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
                // append attribution
                try {
                  const attribution = imageFilename ? ('Image: ' + imageFilename + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
                  if (!formData.value.notes || formData.value.notes.trim() === '') {
                    formData.value.notes = attribution;
                  } else if (formData.value.notes.indexOf(attribution) === -1) {
                    formData.value.notes += '\n\n' + attribution;
                  }
                } catch (e) {}
              } else {
                console.debug('fetchFromWikipedia: proxy did not return image', proxyResp);
              }
            } catch (e) {
              console.debug('fetchFromWikipedia: proxy fetch failed', e);
            }
          }

          if (imgResp && imgResp.ok) {
            const contentType = imgResp.headers.get('Content-Type') || '';
            const buf = await imgResp.arrayBuffer();
            const base64Data = arrayBufferToBase64(buf);
            console.log('fetchFromWikipedia: downloaded image; contentType=', contentType, 'bytes=', buf.byteLength);
            fetchImageStatus.value = 'Cover image downloaded';
            const subtype = contentType && contentType.indexOf('/') !== -1 ? contentType.split('/')[1] : (imageFilename ? imageFilename.split('.').pop() : 'jpeg');
            // set preview and form image (formData.image.data expects raw base64 without data: prefix)
            previewUrl.value = 'data:' + (contentType || ('image/' + subtype)) + ';base64,' + base64Data;
            formData.value.image = {
              name: imageFilename || ('image.' + subtype),
              type: subtype,
              data: base64Data
            };
            console.log('fetchFromWikipedia: assigned formData.image, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
            fetchImageStatus.value = 'Cover image assigned to form';

            // Append short attribution to notes if not already present
            try {
              const attribution = imageFilename ? ('Image: ' + imageFilename + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
              if (!formData.value.notes || formData.value.notes.trim() === '') {
                formData.value.notes = attribution;
              } else if (formData.value.notes.indexOf(attribution) === -1) {
                formData.value.notes += '\n\n' + attribution;
              }
            } catch (e) {
              // ignore attribution failures
            }
          } else {
            console.debug('fetchFromWikipedia: image fetch ultimately failed, imgResp=', imgResp);
          }
        }
        // Fallback: if no imageUrl found yet, use the Wikipedia API to list images on the page
        // (avoids CORS issues fetching raw HTML). We'll prefer image titles containing 'poster'
        if (!imageUrl) {
          try {
            fetchImageStatus.value = 'Searching Wikipedia images list…';
            const imagesApi = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(pageTitle) + '&prop=images&imlimit=50&format=json&origin=*';
            const imagesResp = await fetch(imagesApi, { signal }).then(r => r.json());
            console.debug('fetchFromWikipedia: images list response', imagesResp);
            const pagesObj = imagesResp?.query?.pages || {};
            const pg = Object.values(pagesObj)[0] || {};
            const imgs = pg.images || [];
            // Filter for likely poster files (jpg/png/webp) and prefer ones with 'poster' in the name
            const candidates = imgs.map(i => i.title).filter(Boolean).filter(t => /\.(jpg|jpeg|png|webp)$/i.test(t));
            let chosenFile = null;
            for (const c of candidates) {
              if (/poster/i.test(c)) { chosenFile = c; break; }
            }
            if (!chosenFile && candidates.length > 0) chosenFile = candidates[0];
            if (chosenFile) {
              // Now request imageinfo to get the direct URL (this API supports origin=*)
              fetchImageStatus.value = 'Resolving file URL…';
              const infoApi = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(chosenFile) + '&prop=imageinfo&iiprop=url&format=json&origin=*';
              const infoResp = await fetch(infoApi, { signal }).then(r => r.json());
              console.debug('fetchFromWikipedia: imageinfo response', infoResp);
              const infoPages = infoResp?.query?.pages || {};
              const infoPg = Object.values(infoPages)[0] || {};
              const imageinfo = infoPg.imageinfo || [];
              if (imageinfo.length > 0 && imageinfo[0].url) {
                imageUrl = imageinfo[0].url;
                imageFilename = chosenFile.replace(/^File:/i, '');
                console.debug('fetchFromWikipedia: resolved image URL from imageinfo', imageUrl);
                // Attempt to download/assign the resolved image immediately
                try {
                  const assigned = await downloadAndAssign(imageUrl, imageFilename);
                  if (assigned) {
                    // image assigned, skip remaining attempts
                    // no-op here; subsequent code will see formData.image and not overwrite
                  }
                } catch (e) {
                  console.debug('fetchFromWikipedia: downloadAndAssign failed after imageinfo', e);
                }
              } else {
                console.debug('fetchFromWikipedia: imageinfo did not return a url for', chosenFile, infoResp);
              }
            } else {
              console.debug('fetchFromWikipedia: no image candidates found in images list for', pageTitle, imgs);
            }
          } catch (e) {
            if (e && e.name === 'AbortError') throw e;
            console.debug('fetchFromWikipedia: error querying images API', e);
          }
        }
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        console.debug('Poster fetch failed', e);
        fetchImageStatus.value = 'Cover image fetch failed';
      }

      // If no image was assigned by the poster fetch attempts, update status so the UI doesn't stay stuck
      if (!formData.value.image && !previewUrl.value) {
        fetchImageStatus.value = 'No cover image found';
      }

    // 2) get wikitext revision
  const revUrl = 'https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&rvslots=*&titles=' + encodeURIComponent(pageTitle) + '&format=json&utf8=1&origin=*';
  const revRes = await fetch(revUrl, { signal }).then(r => r.json());
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
      // If Wikidata already populated actors, merge unique values and prefer Wikidata order
      if (wikidataUsed.value && actorsArray.value.length > 0) {
        const existing = actorsArray.value.slice();
        for (const p of parts) {
          if (!existing.includes(p)) existing.push(p);
        }
        actorsArray.value = existing;
        formData.value.actors = existing.join(', ');
      } else {
        actorsArray.value = parts;
        formData.value.actors = parts.join(', ');
      }
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
      // split and clean terms, remove words like 'film'
      const rawParts = genreRaw.split(',').map(s => cleanGenreTerm(s)).filter(Boolean);
      // also split on slashes or pipes
      let parts = [];
      rawParts.forEach(p => {
        parts.push(...p.split(/[|\/]+/).map(x => x.trim()).filter(Boolean));
      });
      // dedupe case-insensitive
      const seen = new Set();
      parts = parts.map(p => p.trim()).filter(Boolean).filter(p => {
        const k = p.toLowerCase();
        if (seen.has(k)) return false;
        seen.add(k);
        return true;
      });
      genresArray.value = parts;
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
      const extResp = await fetch(extractUrl2, { signal }).then(r => r.json());
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
    if (err && err.name === 'AbortError') {
      // aborted by user / new request — don't surface as an error
      return;
    }
    wikiError.value = 'Failed to fetch from Wikipedia.';
    console.error('Wikipedia fetch error', err);
  } finally {
    wikiLoading.value = false;
    // clear controller when done
    try { if (wikiController) { wikiController = null; } } catch (e) {}
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

// Parse ISO 8601 duration (e.g. PT2H3M, PT120M) to minutes. Returns integer minutes or null.
function parseIsoDurationToMinutes(v) {
  if (!v) return null;
  // If it's a plain number, return it
  if (!isNaN(Number(v))) return Math.floor(Number(v));
  // Match patterns like PT2H, PT2H30M, PT120M
  const m = String(v).match(/P(T)?(?:(\d+)H)?(?:(\d+)M)?/i);
  if (m) {
    const hours = parseInt(m[2] || '0', 10) || 0;
    const mins = parseInt(m[3] || '0', 10) || 0;
    return hours * 60 + mins;
  }
  // Try to extract any number
  const m2 = String(v).match(/(\d+)/);
  if (m2) return parseInt(m2[1], 10);
  return null;
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
  // abort any in-flight wiki lookup
  try { if (wikiController) wikiController.abort(); } catch (e) {}
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
    // initialize genres array from incoming data (comma separated or array)
    if (newDvd.genre && typeof newDvd.genre === 'string') {
      genresArray.value = newDvd.genre.split(',').map(s => cleanGenreTerm(s)).filter(Boolean);
    } else if (Array.isArray(newDvd.genre)) {
      genresArray.value = newDvd.genre.map(s => cleanGenreTerm(s)).filter(Boolean);
    } else if (newDvd.genre && String(newDvd.genre).trim() !== '') {
      genresArray.value = String(newDvd.genre).split(',').map(s => cleanGenreTerm(s)).filter(Boolean);
    } else {
      genresArray.value = [];
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
        // set legacy `stars` field so backend continues to receive the actors list
        formData.value.stars = actorsArray.value.join(', ');
        // sync genres tokens back into formData as comma-separated string
        formData.value.genre = genresArray.value.join(', ');

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

      // Debug: log final image state just before emitting so we can see if the image
      // made it into the payload. This helps diagnose cases where fetch succeeded
      // locally but the payload ends up without image.data.
      try {
        const img = formData.value.image;
        if (img && img.data) {
          console.debug('DvdForm.save: emitting image present, data length =', img.data.length);
        } else if (img) {
          console.debug('DvdForm.save: emitting image present but no data (image) =', img);
        } else {
          console.debug('DvdForm.save: emitting with no image');
        }
      } catch (e) {
        console.debug('DvdForm.save: image debug failed', e);
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

// Delete handler: confirm then call backend and notify parent
const confirmAndDelete = async () => {
  const id = formData.value.dkey || formData.value.id;
  if (!id) {
    // nothing to delete
    return;
  }
  const ok = window.confirm('Delete this DVD? This action cannot be undone.');
  if (!ok) return;
  try {
    // Call API to delete by dkey
    await dvdApi.deleteDvd(id);
    // notify parent so it can refresh list and close modal
    emit('deleted', id);
  } catch (e) {
    console.error('Failed to delete DVD', e);
    // surface error to user
    try { alert('Failed to delete DVD: ' + (e && e.message ? e.message : e)); } catch (ee) {}
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

// --- Genres token input helpers ---
const focusGenreInput = () => {
  if (genreInput.value && isEditing.value) genreInput.value.focus();
};

const addGenre = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => cleanGenreTerm(s)).map(s => s.trim()).filter(Boolean);
  for (const p of parts) {
    // dedupe case-insensitive
    if (!genresArray.value.map(x => x.toLowerCase()).includes(p.toLowerCase())) genresArray.value.push(p);
  }
  genreInputValue.value = '';
};

const removeGenre = (idx) => {
  genresArray.value.splice(idx, 1);
};

const onGenreKeydown = (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addGenre(genreInputValue.value);
  } else if (e.key === 'Backspace' && (!genreInputValue.value || genreInputValue.value.length === 0)) {
    genresArray.value.pop();
  }
};

const onGenreBlur = () => {
  if (genreInputValue.value && genreInputValue.value.trim()) {
    addGenre(genreInputValue.value);
  }
};

// token edit handlers for genres
const startEditGenre = (idx) => {
  if (!isEditing.value) return;
  genreEditIndex.value = idx;
  genreEditValue.value = genresArray.value[idx] || '';
  nextTick(() => {
    if (genreEditInput.value) genreEditInput.value.focus();
  });
};

const commitGenreEdit = () => {
  const idx = genreEditIndex.value;
  if (idx < 0) return;
  const v = (genreEditValue.value || '').trim();
  if (v === '') {
    genresArray.value.splice(idx, 1);
  } else {
    genresArray.value.splice(idx, 1, cleanGenreTerm(v));
  }
  genreEditIndex.value = -1;
  genreEditValue.value = '';
};

const onGenreEditKeydown = (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    commitGenreEdit();
  } else if (e.key === 'Escape') {
    genreEditIndex.value = -1;
    genreEditValue.value = '';
  }
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
  display: block;
  padding: 20px 25px;
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
  display: grid;
  grid-template-columns: 200px 1fr; /* left column for cover, right for fields */
  gap: 20px;
}

.right-column {
  /* ensure the right-side container sits in the right grid column and aligns with the cover */
  grid-column: 2;
  align-self: start;
}

.form-row {
  grid-column: 2; /* place paired fields in the right column */
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 6px;
}

.form-group {
  display: flex;
  flex-direction: column;
  grid-column: 2; /* default: place in right column */
}

.form-group.full-width {
  grid-column: 1 / -1; /* span both columns (under the cover) */
}

.form-row .form-group {
  grid-column: auto; /* allow children inside .form-row to flow into the two columns */
}

.form-group.full-width {
  grid-column: 1 / -1;
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
  margin-bottom: 6px;
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

.fetch-image-status {
  margin-left: 8px;
  font-size: 0.85rem;
  color: #374151;
  margin-top: 6px;
}

.btn-fetch[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}

.wikidata-badge {
  display: inline-block;
  margin-left: 8px;
  padding: 6px 8px;
  background: #f1f5f9;
  color: #0f172a;
  border-radius: 999px;
  font-size: 0.85rem;
  border: 1px solid #e2e8f0;
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
  grid-column: 1 / -1; /* span both columns so buttons sit to the right under the form */
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

.btn-delete {
  background-color: #e53e3e;
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  margin-right: auto; /* push following buttons to the right */
}
.btn-delete:hover {
  background-color: #c53030;
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
