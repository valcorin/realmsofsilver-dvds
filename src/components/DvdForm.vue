<template>
  <div class="modal-overlay" v-if="dvd" @click="close">
    <div class="modal-container" @click.stop>
      <div class="form-header">
  <h2>{{ isEditing ? (isNew ? 'New DVD' : 'Edit DVD') : 'DVD Details' }}</h2>
        <button @click="close" class="btn-close">✕</button>
      </div>

      <div class="form-content">
        <!-- Inline chooser shown when server-deterministic poster and another candidate conflict -->
        <div v-if="showServerChooser" class="server-chooser-overlay">
          <div class="server-chooser" @click.stop>
            <h3 style="margin:0 0 10px 0;">Choose cover image</h3>
            <div class="server-chooser-grid">
              <div class="chooser-card">
                <div class="chooser-label">Server conventional poster</div>
                <img v-if="competingCandidate && competingCandidate.server && competingCandidate.server.data"
                  :src="'data:' + (competingCandidate.server.contentType || 'image/jpeg') + ';base64,' + competingCandidate.server.data"
                  class="chooser-img" />
                <div class="chooser-fname">{{ (competingCandidate && competingCandidate.server &&
                  (competingCandidate.server.resolved_file || '')) }}</div>
                <button class="btn-primary" @click="chooseServer">Use server poster</button>
              </div>
              <div class="chooser-card">
                <div class="chooser-label">Other candidate</div>
                <img v-if="competingCandidate && competingCandidate.other && competingCandidate.other.data"
                  :src="'data:' + (competingCandidate.other.contentType || 'image/jpeg') + ';base64,' + competingCandidate.other.data"
                  class="chooser-img" />
                <div class="chooser-fname">{{ (competingCandidate && competingCandidate.other &&
                  competingCandidate.other.filename) }}</div>
                <button class="btn-secondary" @click="chooseOther">Use this image</button>
              </div>
            </div>
            <div style="text-align:center; margin-top:10px;"><button class="btn-secondary"
                @click="showServerChooser = false">Cancel</button></div>
          </div>
        </div>
        <form @submit.prevent="save" class="dvd-form">
          <!-- DVD Cover Image and upload (moved inside the form so fields can span under it) -->
          <div class="dvd-image-section">
            <!-- show preview of newly selected file first, otherwise existing image -->
            <img v-if="previewUrl" :src="previewUrl" :alt="formData.title" class="dvd-cover" />
            <img v-else-if="dvdImageUrl" :src="dvdImageUrl" :alt="formData.title" class="dvd-cover" />
            <!-- File input only available when editing (small, left under the cover) -->
            <div v-if="isEditing" class="file-controls">
              <input type="file" accept="image/*" @change="onFileChange" />
            </div>
          </div>
          <div class="right-column">
            <div class="form-group">
              <label for="title">Title:</label>
              <div class="title-row">
                <template v-if="isEditing">
                  <input ref="titleInput" v-model="formData.title" id="title" type="text" required />

                  <button type="button" class="btn-fetch" :disabled="!formData.title || wikiLoading"
                    @click="fetchFromWikipedia" title="Fetch details from Wikipedia">
                    <span v-if="!wikiLoading">Fetch</span>
                    <span v-else>Loading…</span>
                  </button>
                  <div class="fetch-image-status" v-if="fetchImageStatus">{{ fetchImageStatus }}</div>
                </template>
                <template v-else>
                  <div class="plain-value" id="title">{{ formData.title }}</div>
                </template>
              </div>
              <div class="fetch-error" v-if="wikiError">{{ wikiError }}</div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="year">Year:</label>
                <template v-if="isEditing">
                  <input v-model.number="formData.year" id="year" type="number" required />
                </template>
                <template v-else>
                  <div class="plain-value" id="year">{{ formData.year }}</div>
                </template>
              </div>

              <div class="form-group">
                <label for="rating">Rating:</label>
                <template v-if="isEditing">
                  <input v-model="formData.rating" id="rating" type="text" />
                </template>
                <template v-else>
                  <div class="plain-value" id="rating">{{ formData.rating || '' }}</div>
                </template>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="runtime">Runtime:</label>
                <template v-if="isEditing">
                  <input v-model="formData.runtime" id="runtime" type="text" />
                </template>
                <template v-else>
                  <div class="plain-value" id="runtime">{{ formData.runtime || '' }}</div>
                </template>
              </div>

              <div class="form-group">
                <label for="format">Format:</label>
                <template v-if="isEditing">
                  <select v-model="formData.format" id="format">
                    <option value="DVD">DVD</option>
                    <option value="BLU">Blu-ray</option>
                    <option value="4K">4K UHD</option>
                    <option value="DIG">Digital</option>
                    <option value="BCK">Backup</option>
                    <option value="VHS">VHS</option>
                  </select>
                </template>
                <template v-else>
                  <div class="plain-value" id="format">{{ formatLabel(formData.format) }}</div>
                </template>
              </div>
              <!-- music input moved to full-width token area below; keep this column for layout alignment -->
              <div class="form-group" aria-hidden="true"></div>
            </div>


            <div class="form-group full-width">
              <label for="genre">Genre:</label>
              <!-- shrink-to-fit for genre tokens so the box sizes to its content -->
              <div class="actor-input actor-input--shrink" :class="{ disabled: !isEditing }" @click="focusGenreInput">
                <template v-for="(g, idx) in genresArray" :key="idx">
                  <span v-if="genreEditIndex !== idx" class="actor-token" @click.stop="startEditGenre(idx)">
                    {{ g }}
                    <button v-if="isEditing" type="button" class="token-remove"
                      @click.stop="removeGenre(idx)">✕</button>
                  </span>
                  <input v-else ref="genreEditInput" class="token-edit-input" v-model="genreEditValue"
                    @keydown="onGenreEditKeydown" @blur="commitGenreEdit" />
                </template>
                <input ref="genreInput" v-show="isEditing && genreEditIndex === -1" v-model="genreInputValue"
                  @keydown="onGenreKeydown" @blur="onGenreBlur" placeholder="Add genre and press Enter or comma" />
                <div v-if="!isEditing && genresArray.length === 0" class="hint">No genre listed</div>
              </div>
            </div>

            <div class="form-group full-width image-actions">
              <button v-if="isEditing && (formData.image || previewUrl)" @click.prevent="removeImage" class="btn-secondary">Remove image</button>
              <small v-if="isEditing && formData.image && !previewUrl">Current: {{ formData.image.name || 'existing image' }}</small>
            </div>

          </div> <!-- .right-column -->

          <div class="form-group full-width">
            <label for="directors">Directors:</label>
            <div v-if="isEditing" class="actor-input" :class="{ disabled: !isEditing }" @click="focusDirectorInput">
              <template v-for="(dir, idx) in directorsArray" :key="idx">
                <span v-if="directorEditIndex !== idx" class="actor-token" @click.stop="startEditDirector(idx)">
                  {{ dir }}
                  <button v-if="isEditing" type="button" class="token-remove"
                    @click.stop="removeDirector(idx)">✕</button>
                </span>
                <input v-else ref="directorEditInput" class="token-edit-input" v-model="directorEditValue"
                  @keydown="onDirectorEditKeydown" @blur="commitDirectorEdit" />
              </template>
              <input ref="directorInput" v-show="isEditing && directorEditIndex === -1" v-model="directorInputValue"
                @keydown="onDirectorKeydown" @blur="onDirectorBlur"
                placeholder="Add director and press Enter or comma" />
              <div v-if="isEditing && directorsArray.length === 0" class="hint">No director listed</div>
            </div>
            <div v-else class="plain-list">
              <span v-if="directorsArray && directorsArray.length">{{ directorsArray.join(', ') }}</span>
              <span v-else class="hint">No director listed</span>
            </div>
          </div>

          <div class="form-group full-width">
            <label for="music">Music / Composer:</label>
            <div v-if="isEditing" class="actor-input" :class="{ disabled: !isEditing }" @click="focusMusicInput">
              <template v-for="(m, idx) in musicArray" :key="idx">
                <span v-if="musicEditIndex !== idx" class="actor-token" @click.stop="startEditMusic(idx)">
                  {{ m }}
                  <button v-if="isEditing" type="button" class="token-remove" @click.stop="removeMusic(idx)">✕</button>
                </span>
                <input v-else ref="musicEditInput" class="token-edit-input" v-model="musicEditValue"
                  @keydown="onMusicEditKeydown" @blur="commitMusicEdit" />
              </template>
              <input ref="musicInput" v-show="isEditing && musicEditIndex === -1" v-model="musicInputValue"
                @keydown="onMusicKeydown" @blur="onMusicBlur" placeholder="Add composer and press Enter or comma" />
              <div v-if="isEditing && musicArray.length === 0" class="hint">No composer listed</div>
            </div>
            <div v-else class="plain-list">
              <span v-if="musicArray && musicArray.length">{{ musicArray.join(', ') }}</span>
              <span v-else class="hint">No composer listed</span>
            </div>
          </div>

          <div class="form-group full-width">
            <label for="actors">Actors:</label>
            <div v-if="isEditing" class="actor-input" :class="{ disabled: !isEditing }" @click="focusActorInput">
              <template v-for="(actor, idx) in actorsArray" :key="idx">
                <span v-if="actorEditIndex !== idx" class="actor-token" @click.stop="startEditActor(idx)">
                  {{ actor }}
                  <button v-if="isEditing" type="button" class="token-remove" @click.stop="removeActor(idx)">✕</button>
                </span>
                <input v-else ref="actorEditInput" class="token-edit-input" v-model="actorEditValue"
                  @keydown="onActorEditKeydown" @blur="commitActorEdit" />
              </template>
              <input ref="actorInput" v-show="isEditing && actorEditIndex === -1" v-model="actorInputValue"
                @keydown="onActorKeydown" @blur="onActorBlur" placeholder="Add actor and press Enter or comma" />
              <div v-if="isEditing && actorsArray.length === 0" class="hint">No actors listed</div>
            </div>
            <div v-else class="plain-list">
              <span v-if="actorsArray && actorsArray.length">{{ actorsArray.join(', ') }}</span>
              <span v-else class="hint">No actors listed</span>
            </div>
          </div>

          <div class="form-group full-width">
            <label for="notes">Notes:</label>
            <div v-if="!isEditing" class="notes-view" id="notes">{{ formData.notes }}</div>
            <textarea v-else v-model="formData.notes" id="notes" rows="5"></textarea>
          </div>

          <div class="form-actions">
            <template v-if="!isEditing">
              <button @click="startEdit" type="button" class="btn-primary" :disabled="!props.isAdmin"
                :aria-disabled="!props.isAdmin">Edit</button>
            </template>
            <template v-else>
              <button v-if="!isNew" type="button" class="btn-delete" @click.prevent="confirmAndDelete"
                :disabled="!props.isAdmin" :aria-disabled="!props.isAdmin">Delete</button>
              <button type="submit" class="btn-primary" :disabled="!props.isAdmin"
                :aria-disabled="!props.isAdmin">Save</button>
              <button @click="cancel" type="button" class="btn-secondary">Cancel</button>
            </template>
            <button v-if="!isEditing" @click="close" type="button" class="btn-secondary">Close</button>
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
  },
  // passed from App.vue to gate mutating actions
  isAdmin: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update-dvd', 'close', 'deleted']);

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

// Music (composer) token input state
const musicArray = ref([]);
const musicInputValue = ref('');
const musicInput = ref(null);
// editing state for a music token
const musicEditIndex = ref(-1);
const musicEditValue = ref('');
const musicEditInput = ref(null);

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

// Reject tokens that are plain Wikidata QIDs (e.g. Q217004). Returns true if
// the string is exactly a 'Q' or 'q' followed by one or more digits.
function isQid(s) {
  if (!s) return false;
  return /^[Qq]\d+$/.test(String(s).trim());
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
// Holds an attribution string temporarily so we don't overwrite the article description
let pendingAttribution = '';
// Controller to cancel in-flight wiki/wikidata requests
let wikiController = null;

// Server-side chooser state (moved to component scope so template can access it)
const serverCandidate = ref(null);
const showServerChooser = ref(false);
const competingCandidate = ref(null);
let _chooserResolve = null;

const awaitServerChoice = (srv, other) => {
  console.debug('awaitServerChoice invoked, srv=', srv, 'other=', other);
  return new Promise((resolve) => {
    _chooserResolve = (v) => {
      try { showServerChooser.value = false; } catch (e) { }
      competingCandidate.value = null;
      _chooserResolve = null;
      console.debug('awaitServerChoice resolved with', v);
      resolve(v);
    };
    competingCandidate.value = { server: srv, other };
    showServerChooser.value = true;
    console.debug('awaitServerChoice: showServerChooser set true, competingCandidate=', competingCandidate.value);
  });
};

const chooseServer = () => {
  console.debug('chooseServer clicked');
  if (_chooserResolve) _chooserResolve(true);
};

const chooseOther = () => {
  console.debug('chooseOther clicked');
  if (_chooserResolve) _chooserResolve(false);
};

const applyServerCandidate = (srv) => {
  try {
    console.debug('applyServerCandidate called with', srv);
    const contentType = srv.contentType || 'image/jpeg';
    const b64 = srv.data;
    try { previewUrl.value = 'data:' + contentType + ';base64,' + b64; } catch (e) { previewUrl.value = null; }
    let fname = '';
    try { if (srv.resolved_file) fname = String(srv.resolved_file).replace(/^File:/i, ''); } catch (e) { fname = ''; }
    const subtype = (contentType && contentType.indexOf('/') !== -1) ? contentType.split('/')[1] : 'jpeg';
    formData.value.image = { name: fname || ('image.' + subtype), type: subtype, data: b64 };
    fetchImageStatus.value = 'Cover image assigned (server)';
    try {
      const attribution = fname ? ('Image: ' + fname + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
      if (!formData.value.notes || formData.value.notes.trim() === '') {
        pendingAttribution = attribution;
      } else if (formData.value.notes.indexOf(attribution) === -1) {
        formData.value.notes += '\n\n' + attribution;
      }
    } catch (e) { }
  } catch (e) {
    console.debug('applyServerCandidate failed', e);
  }
};

const fetchFromWikipedia = async () => {
  const title = (formData.value.title || '').trim();
  if (!title) return;
  // abort any previous fetch in progress
  if (wikiController) {
    try { wikiController.abort(); } catch (e) { }
    wikiController = null;
  }
  wikiController = new AbortController();
  const signal = wikiController.signal;

  wikiLoading.value = true;
  wikiError.value = null;
  fetchImageStatus.value = '';
  // Ask user whether to overwrite existing data or only fill empty fields when actors exist
  let overwriteMode = true;
  try {
    if (actorsArray.value && actorsArray.value.length > 0) {
      // Use a simple confirm: OK = Overwrite all fields, Cancel = Fill only empty fields
      const ok = window.confirm('Actors field already contains data. Click OK to OVERWRITE all fields with Wikipedia/Wikidata data; Click Cancel to only FILL empty fields.');
      overwriteMode = !!ok;
    }
  } catch (e) {
    // If window.confirm isn't available or fails, default to overwrite
    overwriteMode = true;
  }
  try {
    // 1) search for the page — fetch several results (top 10), then use Wikidata to
    // confirm which candidates are films. From that subset, pick the candidate whose
    // title most closely matches the user's input title.
    const searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' + encodeURIComponent(title) + '&format=json&utf8=1&srlimit=10&origin=*';
    const sr = await fetch(searchUrl, { signal }).then(r => r.json());
    const hits = sr?.query?.search || [];
    let hit = null;
    if (hits.length > 0) {
      // helper: normalize a title for comparison
      const normalize = (s) => {
        if (!s) return '';
        let t = String(s).toLowerCase().trim();
        // remove parenthetical suffixes: "Foo (2009 film)" -> "Foo"
        t = t.replace(/\s*\([^)]*\)\s*$/, '');
        // drop leading "the "
        t = t.replace(/^the\s+/i, '');
        // remove non-alphanumeric characters
        t = t.replace(/[^a-z0-9\s]/g, '');
        t = t.replace(/\s+/g, ' ').trim();
        return t;
      };

      // simple Levenshtein distance for tie-breaking
      const levenshtein = (a, b) => {
        if (a === b) return 0;
        const al = a.length, bl = b.length;
        if (al === 0) return bl;
        if (bl === 0) return al;
        const v0 = new Array(bl + 1).fill(0).map((_, i) => i);
        const v1 = new Array(bl + 1).fill(0);
        for (let i = 0; i < al; i++) {
          v1[0] = i + 1;
          for (let j = 0; j < bl; j++) {
            const cost = a[i] === b[j] ? 0 : 1;
            v1[j + 1] = Math.min(v1[j] + 1, v0[j + 1] + 1, v0[j] + cost);
          }
          for (let j = 0; j <= bl; j++) v0[j] = v1[j];
        }
        return v1[bl];
      };

      const queryNorm = normalize(title);
      const filmCandidates = [];

      // Check candidates serially (to avoid too many parallel requests); collect confirmed films
      for (const candidate of hits) {
        try {
          // fetch pageprops to resolve wikibase_item
          const propsUrlCandidate = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(candidate.title) + '&prop=pageprops&format=json&origin=*';
          const propsRespCand = await fetch(propsUrlCandidate, { signal }).then(r => r.json());
          const pagesPropsCand = propsRespCand?.query?.pages || {};
          const pageObjCand = Object.values(pagesPropsCand)[0] || {};
          const wikibaseItemCand = pageObjCand.pageprops && pageObjCand.pageprops.wikibase_item;
          if (wikibaseItemCand) {
            try {
              const entUrl = 'https://www.wikidata.org/wiki/Special:EntityData/' + encodeURIComponent(wikibaseItemCand) + '.json';
              const entResp = await fetch(entUrl, { signal }).then(r => r.json());
              const entities = entResp?.entities || {};
              const ent = entities[wikibaseItemCand] || Object.values(entities)[0] || {};
              const claims = ent.claims || {};
              const p31 = claims.P31 || [];
              const isFilm = p31.some(cl => {
                const v = cl.mainsnak && cl.mainsnak.datavalue && cl.mainsnak.datavalue.value;
                if (!v) return false;
                if (typeof v === 'string') return v === 'Q11424';
                if (v && v.id) return v.id === 'Q11424';
                return false;
              });
              if (isFilm) {
                filmCandidates.push(candidate);
              }
            } catch (e) {
              console.debug('Wikidata entity check failed for', wikibaseItemCand, e);
            }
          }
        } catch (e) {
          if (e && e.name === 'AbortError') throw e;
        }
      }

      // If we have film candidates, pick the one most similar to the query title
      if (filmCandidates.length > 0) {
        let best = null;
        let bestScore = Infinity;
        for (const c of filmCandidates) {
          const candNorm = normalize(c.title);
          let score = 0;
          if (candNorm === queryNorm) {
            score = 0;
          } else if (candNorm.includes(queryNorm) || queryNorm.includes(candNorm)) {
            score = 1;
          } else {
            score = levenshtein(candNorm, queryNorm);
          }
          if (score < bestScore) {
            bestScore = score;
            best = c;
          }
        }
        hit = best;
      } else {
        // fall back to title heuristics if no film candidates confirmed via Wikidata
        hit = hits.find(h => /\(\s*\d{4}\s+film\s*\)/i.test(h.title)) || hits.find(h => /\(\s*film\s*\)/i.test(h.title)) || hits[0];
      }
    }
    if (!hit) {
      wikiError.value = 'No Wikipedia page found for this title.';
      return;
    }
    const apiTitle = hit.title; // canonical title returned by Wikipedia (use for API requests)
    // Use the canonical Wikipedia title for lookups, but store a cleaned
    // display-friendly title in the form (remove a leading 'The ' so the
    // saved title matches the UI expectation). Keep `pageTitle` as the
    // canonical apiTitle for further API calls.
    try {
      let cleaned = String(apiTitle || '').trim();
      // strip trailing parenthetical containing the word 'film' (e.g. "(film)")
      cleaned = cleaned.replace(/\s*\([^)]*\bfilm\b[^)]*\)\s*$/i, '').trim();
      // remove a leading 'The ' (case-insensitive)
      cleaned = cleaned.replace(/^\s*the\s+/i, '').trim();
      formData.value.title = cleaned || apiTitle;
    } catch (e) {
      formData.value.title = apiTitle;
    }
    // Use apiTitle for all following API calls
    const pageTitle = apiTitle;

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
        const sparql = `SELECT ?directorLabel ?castLabel ?genreLabel ?composerLabel ?duration ?publicationDate WHERE {
            wd:${wikibaseItem} wdt:P31 wd:Q11424 .
            OPTIONAL { wd:${wikibaseItem} wdt:P57 ?director. }
            OPTIONAL { wd:${wikibaseItem} wdt:P161 ?cast. }
            OPTIONAL { wd:${wikibaseItem} wdt:P86 ?composer. }
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
        const composerList = [];
        let durationVal = null;
        let pubDateVal = null;

        const pick = (row, keys) => {
          for (const k of keys) {
            if (row[k] && row[k].value) return row[k].value;
          }
          return null;
        };

        // Normalize SPARQL binding values: prefer human-readable labels; if the
        // value is a Wikidata entity URI (or bare QID), treat it as missing so we
        // don't insert raw QIDs into token lists.
        const normalizeSparqlValue = (v) => {
          if (!v) return null;
          const s = String(v).trim();
          // Examples to reject: 'http://www.wikidata.org/entity/Q12345', 'wd:Q12345', 'Q12345'
          if (/\bQ\d+\b/i.test(s)) {
            // If it's exactly a QID, or contains an entity Q, reject it (label should be present)
            if (/^(?:wd:)?Q\d+$/i.test(s) || /entity\/Q\d+/i.test(s)) return null;
          }
          return s;
        };

        rows.forEach(row => {
          const d = normalizeSparqlValue(pick(row, ['directorLabel', 'director']));
          const c = normalizeSparqlValue(pick(row, ['castLabel', 'cast']));
          const g = normalizeSparqlValue(pick(row, ['genreLabel', 'genre']));
          const dur = normalizeSparqlValue(pick(row, ['duration']));
          const pub = normalizeSparqlValue(pick(row, ['publicationDate']));
          const comp = normalizeSparqlValue(pick(row, ['composerLabel', 'composer']));

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
          if (comp) {
            const kc = String(comp).trim();
            if (kc && !composerList.some(x => x.toLowerCase() === kc.toLowerCase())) composerList.push(kc);
          }
          if (!durationVal && dur) durationVal = dur;
          if (!pubDateVal && pub) pubDateVal = pub;
        });

        if (directorList.length > 0) {
          // respect overwriteMode: only replace if overwriting or no existing directors
          if (overwriteMode || !directorsArray.value || directorsArray.value.length === 0 || !formData.value.directors || String(formData.value.directors).trim() === '') {
            directorsArray.value = directorList;
            formData.value.directors = directorList.join(', ');
            formData.value.director = directorList.join(', ');
          }
        }

        if (castList.length > 0) {
          // respect overwriteMode: only replace if overwriting or no existing actors
          if (overwriteMode || !actorsArray.value || actorsArray.value.length === 0 || !formData.value.actors || String(formData.value.actors).trim() === '') {
            actorsArray.value = castList;
            formData.value.actors = castList.join(', ');
          }
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
          // respect overwriteMode: only replace if overwriting or no existing genres
          if (overwriteMode || !genresArray.value || genresArray.value.length === 0 || !formData.value.genre || String(formData.value.genre).trim() === '') {
            genresArray.value = parts;
            formData.value.genre = parts.join(', ');
          }
        }

        if (composerList.length > 0) {
          // respect overwriteMode: only replace if overwriting or no existing music/composer
          if (overwriteMode || !musicArray.value || musicArray.value.length === 0 || !formData.value.music || String(formData.value.music).trim() === '') {
            musicArray.value = composerList;
            formData.value.music = composerList.join(', ');
            // keep legacy composer field for backward compatibility
            formData.value.composer = composerList.join(', ');
          }
        }

        if (durationVal) {
          // respect overwriteMode: only set runtime if overwriting or runtime empty
          if (overwriteMode || !formData.value.runtime || String(formData.value.runtime).trim() === '') {
            const minutes = parseIsoDurationToMinutes(durationVal);
            if (minutes !== null) formData.value.runtime = String(minutes);
            else formData.value.runtime = String(durationVal);
          }
        }

        if (pubDateVal) {
          // respect overwriteMode: only set year if overwriting or year empty
          try {
            if (overwriteMode || !formData.value.year) {
              const y = new Date(pubDateVal).getFullYear();
              if (!Number.isNaN(y)) formData.value.year = Number(y);
            }
          } catch (e) { }
        }

        if (directorList.length > 0 || castList.length > 0 || genreList.length > 0 || composerList.length > 0 || durationVal || pubDateVal) {
          wikidataUsed.value = true;
          // Instead of showing a badge in the UI, append a short note.
          // If notes are empty, defer appending until after we fetch the article extract
          // (use the existing pendingAttribution mechanism so we don't overwrite the extract).
          try {
            const note = 'Data from Wikidata';
            if (!formData.value.notes || formData.value.notes.trim() === '') {
              if (pendingAttribution.indexOf(note) === -1) {
                if (pendingAttribution && pendingAttribution.trim() !== '') pendingAttribution += '\n\n';
                pendingAttribution += note;
              }
            } else if (formData.value.notes.indexOf(note) === -1) {
              formData.value.notes += '\n\n' + note;
            }
          } catch (e) {
            console.debug('Failed to append Wikidata note', e);
          }
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
      // Flag set when the server returns a deterministic/enwiki-resolved image
      // so we can prefer it over Wikidata P18 results.
      let serverResolvedFlag = false;
      // helpers to prefer poster/promotional images and avoid cosplay/fanart/screenshot files
      const isUndesirableImage = (name) => {
        if (!name) return false;
        // include common misspelling 'cossplay' as well as 'cosplay'
        return /(cosplay|cossplay|fanart|screenshot|still|screencap|screengrab|behind[-_ ]?the[-_ ]?scenes)/i.test(name);
      };
      const isPosterish = (name) => {
        if (!name) return false;
        return /poster|promotional|promo|theatrical|teaser|poster[-_ ]?art/i.test(name);
      };
      fetchImageStatus.value = 'Searching for cover image…';


      // helper to attempt downloading and assigning an image URL (tries server proxy first, then original/thumbnail)
      const downloadAndAssign = async (imageUrlParam, imageFilenameParam) => {
        let imageUrlLocal = imageUrlParam;
        let imageFilenameLocal = imageFilenameParam;
        if (!imageUrlLocal) return false;
        console.log('fetchFromWikipedia: resolved imageUrl=', imageUrlLocal, 'imageFilename=', imageFilenameLocal);

        // Strict rule: avoid any image URL that contains 'cosplay' (case-insensitive).
        // Decode the URL for safety (handles percent-encoding) but fall back to raw string.
        try {
          const decoded = decodeURIComponent(String(imageUrlLocal).toLowerCase());
          if (/(cosplay|cossplay)/i.test(decoded)) {
            console.debug('fetchFromWikipedia: skipping image because URL contains "cosplay/cossplay"', imageUrlLocal);
            fetchImageStatus.value = 'Skipped unsuitable image (cosplay)';
            return false;
          }
        } catch (e) {
          try {
            if (/(cosplay|cossplay)/i.test(String(imageUrlLocal).toLowerCase())) {
              console.debug('fetchFromWikipedia: skipping image because URL contains "cosplay/cossplay"', imageUrlLocal);
              fetchImageStatus.value = 'Skipped unsuitable image (cosplay)';
              return false;
            }
          } catch (ee) { }
        }

        // If the user chose "Fill" (overwriteMode === false), do not replace an
        // existing image/preview/selected file. This prevents Fetch from
        // overwriting a manually-selected or existing cover when the user
        // declined to overwrite.
        try {
          if (typeof overwriteMode !== 'undefined' && !overwriteMode) {
            const hasSelectedFile = !!selectedFile.value;
            const hasPreview = !!previewUrl.value;
            const hasExistingImageData = !!(formData.value.image && (formData.value.image.data || formData.value.image.name));
            if (hasSelectedFile || hasPreview || hasExistingImageData) {
              console.debug('fetchFromWikipedia: skipping image assignment because overwriteMode=false and an image already exists');
              fetchImageStatus.value = 'Cover image left unchanged';
              return false;
            }
          }
        } catch (e) {
          console.debug('fetchFromWikipedia: error checking overwriteMode for image assignment', e);
        }

        // First attempt: server-side proxy (preferred to avoid CORS/redirect issues)
        try {
          fetchImageStatus.value = 'Downloading cover image via server proxy…';
          const proxyUrl = '/api/fetch-image.php?url=' + encodeURIComponent(imageUrlLocal);
          console.debug('fetchFromWikipedia: attempting server-side proxy fetch', proxyUrl);
          const proxyResp = await fetch(proxyUrl, { signal }).then(r => r.json());
          console.debug('fetchFromWikipedia: proxy response', proxyResp);
          if (proxyResp && proxyResp.ok && proxyResp.data) {
            const contentType = proxyResp.contentType || '';
            const base64Data = proxyResp.data;
            // If we have a serverCandidate (deterministic match) and this is a different
            // candidate (e.g., from P18), ask the user which to keep.
            try {
              if (serverCandidate && serverCandidate.value && imageFilenameLocal) {
                const srv = serverCandidate.value;
                const other = { contentType: contentType || '', data: base64Data, filename: imageFilenameLocal };
                try {
                  const useServer = await awaitServerChoice(srv, other);
                  if (useServer) {
                    applyServerCandidate(srv);
                    serverCandidate.value = null;
                    return true;
                  }
                  // else: user chose the other image — continue to assign below
                } catch (e) {
                  // if chooser failed, fall through and assign this image
                  console.debug('server chooser failed', e);
                }
              }
            } catch (e) { }

            previewUrl.value = 'data:' + (contentType || 'image/jpeg') + ';base64,' + base64Data;
            const subtype = (contentType && contentType.indexOf('/') !== -1) ? contentType.split('/')[1] : (imageFilenameLocal ? imageFilenameLocal.split('.').pop() : 'jpeg');
            formData.value.image = {
              name: imageFilenameLocal || ('image.' + subtype),
              type: subtype,
              data: base64Data
            };
            fetchImageStatus.value = 'Cover image assigned via proxy';
            console.log('fetchFromWikipedia: assigned formData.image via proxy, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
            // prepare attribution (don't overwrite potential article extract)
            try {
              const attribution = imageFilenameLocal ? ('Image: ' + imageFilenameLocal + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
              if (!formData.value.notes || formData.value.notes.trim() === '') {
                // hold until after we fetch the article extract so we don't overwrite it
                pendingAttribution = attribution;
              } else if (formData.value.notes.indexOf(attribution) === -1) {
                formData.value.notes += '\n\n' + attribution;
              }
            } catch (e) { }
            return true;
          } else {
            console.debug('fetchFromWikipedia: proxy did not return image', proxyResp);
          }
        } catch (e) {
          console.debug('fetchFromWikipedia: proxy fetch failed, will try client-side fetch', e);
        }

        // Fallback: try client-side fetch
        fetchImageStatus.value = 'Downloading cover image…';
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
          try {
            // If the URL points to a Commons Special:FilePath (or commons page) and we
            // have the filename, try resolving it via the Commons API to get a direct
            // file URL. This avoids CORS/redirects when fetching Special:FilePath URLs.
            try {
              if (imageFilenameLocal && imageUrlLocal && (imageUrlLocal.includes('Special:FilePath') || imageUrlLocal.includes('commons.wikimedia.org'))) {
                const commonsApi = 'https://commons.wikimedia.org/w/api.php?action=query&titles=' + encodeURIComponent('File:' + imageFilenameLocal) + '&prop=imageinfo&iiprop=url&format=json&origin=*';
                console.debug('fetchFromWikipedia: attempting Commons API imageinfo for', imageFilenameLocal, commonsApi);
                try {
                  const infoResp2 = await fetch(commonsApi, { signal }).then(r => r.json());
                  const infoPages2 = infoResp2?.query?.pages || {};
                  const infoPg2 = Object.values(infoPages2)[0] || {};
                  const imageinfo2 = infoPg2.imageinfo || [];
                  if (imageinfo2.length > 0 && imageinfo2[0].url) {
                    imageUrlLocal = imageinfo2[0].url;
                    console.debug('fetchFromWikipedia: resolved commons imageinfo URL', imageUrlLocal);
                    imgResp = await fetch(imageUrlLocal, { signal });
                    console.debug('fetchFromWikipedia: fetched resolved commons URL', { ok: imgResp && imgResp.ok, status: imgResp && imgResp.status });
                  }
                } catch (e) {
                  console.debug('fetchFromWikipedia: Commons API imageinfo request failed', e);
                }
              }
            } catch (e) {
              console.debug('fetchFromWikipedia: commons resolution attempted and failed', e);
            }
            if (!imageFilenameLocal && imageUrlLocal) {
              const parts = imageUrlLocal.split('/');
              imageFilenameLocal = parts[parts.length - 1] || imageFilenameLocal;
            }
            if (imageUrlLocal && imageFilenameLocal && imageUrlLocal.includes('/wikipedia/')) {
              const idx = imageUrlLocal.indexOf('/wikipedia/');
              const after = imageUrlLocal.slice(idx + '/wikipedia/'.length);
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
        }

        if (imgResp && imgResp.ok) {
          const contentType = imgResp.headers.get('Content-Type') || '';
          const buf = await imgResp.arrayBuffer();
          const base64Data = arrayBufferToBase64(buf);
          console.log('fetchFromWikipedia: downloaded image; contentType=', contentType, 'bytes=', buf.byteLength);
          // If serverCandidate exists, ask user whether to prefer that over this download
          try {
            if (serverCandidate && serverCandidate.value && imageFilenameLocal) {
              const srv = serverCandidate.value;
              const other = { contentType: contentType || '', data: base64Data, filename: imageFilenameLocal };
              try {
                const useServer = await awaitServerChoice(srv, other);
                if (useServer) {
                  applyServerCandidate(srv);
                  serverCandidate.value = null;
                  return true; // assigned server image
                }
                // else: user chose the downloaded image; proceed to assign below
              } catch (e) {
                console.debug('server chooser failed', e);
              }
            }
          } catch (e) { }

          fetchImageStatus.value = 'Cover image downloaded';
          const subtype = contentType && contentType.indexOf('/') !== -1 ? contentType.split('/')[1] : (imageFilenameLocal ? imageFilenameLocal.split('.').pop() : 'jpeg');
          previewUrl.value = 'data:' + (contentType || ('image/' + subtype)) + ';base64,' + base64Data;
          formData.value.image = {
            name: imageFilenameLocal || ('image.' + subtype),
            type: subtype,
            data: base64Data
          };
          console.log('fetchFromWikipedia: assigned formData.image, data length=', (formData.value.image && formData.value.image.data) ? formData.value.image.data.length : 0);
          fetchImageStatus.value = 'Cover image assigned to form';
          try {
            const attribution = imageFilenameLocal ? ('Image: ' + imageFilenameLocal + ' (Wikimedia Commons)') : 'Image from Wikimedia Commons';
            if (!formData.value.notes || formData.value.notes.trim() === '') {
              pendingAttribution = attribution;
            } else if (formData.value.notes.indexOf(attribution) === -1) {
              formData.value.notes += '\n\n' + attribution;
            }
          } catch (e) { }
          return true;
        }
        console.debug('fetchFromWikipedia: image fetch ultimately failed, imgResp=', imgResp);
        return false;
      };
      // SERVER-FIRST: Ask the server to attempt deterministic File: pattern resolution
      // Do this before the pageimages and Wikidata fallbacks so deterministic
      // filenames (including The_ variants) are preferred and resolved server-side
      // to avoid browser CORS/redirect issues. If a server candidate and another
      // candidate (e.g., Wikidata P18) both exist, we'll ask the user which to keep.

      try {
        fetchImageStatus.value = 'Checking server for conventional poster filenames…';
        const proxyApi = '/api/fetch-image.php?title=' + encodeURIComponent(pageTitle) + '&tryPatterns=1';
        const srv = await fetch(proxyApi).then(r => r.json());
        if (srv && srv.ok && srv.data) {
          // store candidate but don't force-assign yet — give user a choice if another
          // strong candidate (like Wikidata P18) also appears.
          serverCandidate.value = srv;
          console.debug('fetchFromWikipedia: server deterministic candidate stored in serverCandidate', srv);
          fetchImageStatus.value = 'Server found a conventional poster; awaiting confirmation';
        }
      } catch (e) {
        console.debug('fetchFromWikipedia: server deterministic check failed', e);
      }

      // 1) Wikipedia pageimages API (prefer original, else thumbnail)
      try {
        const piUrl = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(pageTitle) + '&prop=pageimages&piprop=original|thumbnail&pithumbsize=600&format=json&origin=*';
        const piResp = await fetch(piUrl, { signal }).then(r => r.json());
        console.debug('fetchFromWikipedia: pageimages response', piResp);
        const piPages = piResp?.query?.pages || {};
        const piPage = Object.values(piPages)[0] || {};
        imageUrl = piPage?.original?.source || piPage?.thumbnail?.source || null;
        // if the resolved pageimage filename looks undesirable (cosplay/fanart/etc.) and
        // doesn't contain poster/promotional hints, skip using it
        try {
          if (imageUrl) {
            const parts = imageUrl.split('/');
            const fname = parts[parts.length - 1] || '';
            if (isUndesirableImage(fname) && !isPosterish(fname)) {
              console.debug('fetchFromWikipedia: skipping undesirable pageimage filename', fname);
              imageUrl = null;
            }
          }
        } catch (e) { }
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
              // skip undesirable wikidata images unless they indicate poster/promotional
              if (isUndesirableImage(imageFilename) && !isPosterish(imageFilename)) {
                console.debug('fetchFromWikipedia: skipping wikidata P18 undesirable filename', imageFilename);
              } else {
                imageUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/' + encodeURIComponent(imageFilename);
                console.debug('fetchFromWikipedia: wikidata P18 found', imageFilename, imageUrl);
              }
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
        // Use the centralized helper (which now prefers the server proxy first)
        try {
          const assigned = await downloadAndAssign(imageUrl, imageFilename);
          if (!assigned) console.debug('fetchFromWikipedia: downloadAndAssign did not assign an image for', imageUrl);
        } catch (e) {
          console.debug('fetchFromWikipedia: downloadAndAssign threw', e);
        }
      }
      // Ask the server to attempt deterministic File: pattern resolution.
      // The server will resolve Commons File: pages and return the image via the proxy to avoid CORS issues.
      if (!imageUrl) {
        try {
          fetchImageStatus.value = 'Checking server for conventional poster filenames…';
          const proxyApi = '/api/fetch-image.php?title=' + encodeURIComponent(pageTitle) + '&tryPatterns=1';
          const srv = await fetch(proxyApi).then(r => r.json());
          if (srv && srv.ok && srv.data) {
            const contentType = srv.contentType || 'image/jpeg';
            const b64 = srv.data;
            // set preview and form image directly from returned base64
            try {
              previewUrl.value = 'data:' + contentType + ';base64,' + b64;
            } catch (e) {
              previewUrl.value = null;
            }
            const subtype = (contentType && contentType.indexOf('/') !== -1) ? contentType.split('/')[1] : 'jpeg';
            formData.value.image = { name: '', type: subtype, data: b64 };
            fetchImageStatus.value = 'Cover image assigned (server)';
            imageUrl = 'server-resolved';
          }
        } catch (e) {
          console.debug('fetchFromWikipedia: server deterministic check failed', e);
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
          // Filter for likely poster files (jpg/png/webp)
          const candidatesRaw = imgs.map(i => i.title).filter(Boolean).filter(t => /\.(jpg|jpeg|png|webp)$/i.test(t));
          // filter out clearly undesirable images first (cosplay, fanart, screenshots)
          const candidates = candidatesRaw.filter(c => !isUndesirableImage(c));
          let chosenFile = null;
          try {
            // normalize the page title (remove File: prefix and parentheticals)
            const normPage = (pageTitle || '').toLowerCase().replace(/^file:/i, '').replace(/\s*\([^)]*\)\s*$/, '').replace(/[^a-z0-9]+/g, ' ').trim();

            // initial lightweight scoring based on filename
            const scores = candidates.map(c => {
              const fname = (c || '').toLowerCase();
              let score = 1000;
              // exact file match (ignoring File: prefix)
              if (fname === ('file:' + normPage + '.jpg') || fname === (normPage + '.jpg')) score = 0;
              // filename starts with normalized page title (strong signal)
              else if (fname.replace(/^file:/, '').indexOf(normPage) === 0) score = 1;
              // contains normalized page title words
              else if (fname.indexOf(normPage.replace(/\s+/g, ' ')) !== -1) score = 5;
              // poster/promotional hint lowers score
              if (isPosterish(fname) || /poster|promotional|theatrical|promo|cover/i.test(fname)) score -= 3;
              // shorter filenames slightly preferred
              score += Math.min(20, fname.length / 20);
              return { fname: c, score };
            });

            // take top candidates and fetch imageinfo in parallel to prefer upload.wikimedia.org and larger images
            scores.sort((a, b) => a.score - b.score);
            const top = scores.slice(0, 6).map(s => s.fname);
            const enriched = await Promise.all(top.map(async (fname) => {
              try {
                const infoApi2 = 'https://en.wikipedia.org/w/api.php?action=query&titles=' + encodeURIComponent(fname) + '&prop=imageinfo&iiprop=url|size&format=json&origin=*';
                const infoResp2 = await fetch(infoApi2, { signal }).then(r => r.json());
                const pages2 = infoResp2?.query?.pages || {};
                const pg2 = Object.values(pages2)[0] || {};
                const ii = (pg2.imageinfo && pg2.imageinfo[0]) || {};
                const url = ii.url || null;
                const width = ii.width || 0;
                return { fname, url, width };
              } catch (err) {
                return { fname, url: null, width: 0 };
              }
            }));

            // compute final score using filename match, poster hint, host, and image width
            const finalScores = enriched.map(e => {
              const fname = (e.fname || '').toLowerCase();
              let score = 1000;
              if (fname.replace(/^file:/, '') === normPage + '.jpg' || fname === normPage + '.jpg') score = 0;
              else if (fname.replace(/^file:/, '').indexOf(normPage) === 0) score = 1;
              else if (fname.indexOf(normPage.replace(/\s+/g, ' ')) !== -1) score = 10;
              if (isPosterish(fname) || /poster|promotional|theatrical|promo|cover/i.test(fname)) score -= 4;
              // prefer upload.wikimedia.org canonical host
              if (e.url && /upload\.wikimedia\.org/i.test(e.url)) score -= 5;
              // prefer larger images (width) - modest bonus scaled and capped
              if (e.width && e.width > 0) {
                score -= Math.min(10, Math.floor(e.width / 400));
              }
              // small tie-breaker: shorter filename
              score += Math.min(20, fname.length / 30);
              return { fname: e.fname, score, url: e.url };
            });

            finalScores.sort((a, b) => a.score - b.score);
            if (finalScores.length > 0) {
              chosenFile = finalScores[0].fname;
            }
          } catch (e) {
            // fallback to more basic heuristics
            for (const c of candidates) {
              if (isPosterish(c) || /poster/i.test(c)) { chosenFile = c; break; }
            }
            if (!chosenFile && candidates.length > 0) chosenFile = candidates[0];
          }
          // fallback: if all candidates were undesirable, fall back to the raw list
          if (!chosenFile && candidatesRaw.length > 0) chosenFile = candidatesRaw[0];
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
      let parts = [];
      // Handle Plainlist template and pipe-separated lists similar to actors/starring
      if (/\{\{\s*Plainlist\b/i.test(dirRaw)) {
        // extract inside of Plainlist and split on pipes/newlines/bullets
        const m = dirRaw.match(/\{\{\s*Plainlist\s*\|(.*?)\}\}/is);
        if (m) {
          const inner = m[1];
          parts = inner.split(/[|\n\r]+/).map(s => s.replace(/^\s*\*\s*/, '').trim()).filter(Boolean);
        }
      } else if (dirRaw.indexOf('|') !== -1 && dirRaw.indexOf(',') === -1) {
        parts = dirRaw.split('|').map(s => s.trim()).filter(Boolean);
      } else {
        parts = dirRaw.split(',').map(s => s.trim()).filter(Boolean);
      }
      // respect overwriteMode: only set if overwriting or no existing directors
      if (overwriteMode || !directorsArray.value || directorsArray.value.length === 0 || !formData.value.directors || String(formData.value.directors).trim() === '') {
        directorsArray.value = parts;
        formData.value.directors = parts.join(', ');
        formData.value.director = parts.join(', ');
      }
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
      // When in fill mode, only populate actors if empty. When overwrite, keep previous
      // behavior (merge if Wikidata was used and actors exist, else replace).
      if (!actorsArray.value || actorsArray.value.length === 0) {
        actorsArray.value = parts;
        formData.value.actors = parts.join(', ');
      } else if (overwriteMode) {
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
      // If we withheld an attribution earlier because notes were empty, append it now
      try {
        if (pendingAttribution) {
          if (!formData.value.notes || formData.value.notes.trim() === '') {
            formData.value.notes = pendingAttribution;
          } else if (formData.value.notes.indexOf(pendingAttribution) === -1) {
            formData.value.notes += '\n\n' + pendingAttribution;
          }
          pendingAttribution = '';
        }
      } catch (e) { }
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
    try { if (wikiController) { wikiController = null; } } catch (e) { }
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

// Format label helper for display when viewing
function formatLabel(f) {
  const map = {
    'DVD': 'DVD',
    'BLU': 'Blu-ray',
    '4K': '4K UHD',
    'DIG': 'Digital',
    'BCK': 'Backup',
    'VHS': 'VHS'
  };
  return (f && map[f]) ? map[f] : (f || '');
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
  try { if (wikiController) wikiController.abort(); } catch (e) { }
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
      actorsArray.value = newDvd.actors.split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (Array.isArray(newDvd.actors)) {
      actorsArray.value = newDvd.actors.map(s => String(s).trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (newDvd.stars && String(newDvd.stars).trim() !== '') {
      // fallback to legacy `stars` column if `actors` missing
      actorsArray.value = String(newDvd.stars).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      actorsArray.value = [];
    }
    // initialize directors array from incoming data (comma separated or array)
    if (newDvd.directors && typeof newDvd.directors === 'string') {
      directorsArray.value = newDvd.directors.split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (Array.isArray(newDvd.directors)) {
      directorsArray.value = newDvd.directors.map(s => String(s).trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (newDvd.director && String(newDvd.director).trim() !== '') {
      // fallback to legacy `director` column if `directors` missing
      directorsArray.value = String(newDvd.director).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      directorsArray.value = [];
    }
    // initialize genres array from incoming data (comma separated or array)
    if (newDvd.genre && typeof newDvd.genre === 'string') {
      genresArray.value = newDvd.genre.split(',').map(s => cleanGenreTerm(s)).filter(Boolean).filter(p => !isQid(p));
    } else if (Array.isArray(newDvd.genre)) {
      genresArray.value = newDvd.genre.map(s => cleanGenreTerm(s)).filter(Boolean).filter(p => !isQid(p));
    } else if (newDvd.genre && String(newDvd.genre).trim() !== '') {
      genresArray.value = String(newDvd.genre).split(',').map(s => cleanGenreTerm(s)).filter(Boolean);
    } else {
      genresArray.value = [];
    }
    // initialize music (composer) array from incoming data (comma separated or array)
    if (newDvd.music && typeof newDvd.music === 'string') {
      musicArray.value = newDvd.music.split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (Array.isArray(newDvd.music)) {
      musicArray.value = newDvd.music.map(s => String(s).trim()).filter(Boolean).filter(p => !isQid(p));
    } else if (newDvd.composer && String(newDvd.composer).trim() !== '') {
      // fallback if older payload used `composer`
      musicArray.value = String(newDvd.composer).split(',').map(s => s.trim()).filter(Boolean);
    } else {
      musicArray.value = [];
    }
    // If this is a newly-created DVD and we're in edit mode, autofocus title
    if (isEditing.value && (!newDvd.id && !newDvd.dkey)) {
      nextTick(() => {
        try { if (titleInput.value) titleInput.value.focus(); } catch (e) { }
      });
    }
  }
}, { immediate: true });

watch(() => props.editMode, (newMode) => {
  isEditing.value = newMode;
});

const startEdit = () => {
  if (!props.isAdmin) {
    console.warn('Edit blocked: admin required');
    try { alert('Editing is disabled: admin access required.'); } catch (e) { }
    return;
  }
  isEditing.value = true;
};

const save = () => {
  if (!props.isAdmin) {
    console.warn('Save blocked: admin required');
    try { alert('Save is disabled: admin access required.'); } catch (e) { }
    return;
  }
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
      // sync music/composer tokens back into formData as comma-separated string
      formData.value.music = musicArray.value.join(', ');

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
  if (!props.isAdmin) {
    console.warn('Delete blocked: admin required');
    try { alert('Delete is disabled: admin access required.'); } catch (e) { }
    return;
  }
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
    try { alert('Failed to delete DVD: ' + (e && e.message ? e.message : e)); } catch (ee) { }
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
  const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
  for (const p of parts) {
    if (!actorsArray.value.includes(p)) actorsArray.value.push(p);
  }
  actorInputValue.value = '';
};

const addDirector = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
  for (const p of parts) {
    if (!directorsArray.value.includes(p)) directorsArray.value.push(p);
  }
  directorInputValue.value = '';
};

// --- Genres token input helpers ---
const focusGenreInput = () => {
  if (genreInput.value && isEditing.value) genreInput.value.focus();
};

const focusMusicInput = () => {
  if (musicInput.value && isEditing.value) musicInput.value.focus();
};

const addGenre = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => cleanGenreTerm(s)).map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
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

const addMusic = (raw) => {
  if (!raw) return;
  const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean).filter(p => !isQid(p));
  for (const p of parts) {
    if (!musicArray.value.includes(p)) musicArray.value.push(p);
  }
  musicInputValue.value = '';
};

const removeMusic = (idx) => {
  musicArray.value.splice(idx, 1);
};

const onMusicKeydown = (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addMusic(musicInputValue.value);
  } else if (e.key === 'Backspace' && (!musicInputValue.value || musicInputValue.value.length === 0)) {
    musicArray.value.pop();
  }
};

const onMusicBlur = () => {
  if (musicInputValue.value && musicInputValue.value.trim()) {
    addMusic(musicInputValue.value);
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
  if (v === '' || isQid(v)) {
    // remove token if the edit is empty or it's a QID
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

// token edit handlers for music
const startEditMusic = (idx) => {
  if (!isEditing.value) return;
  musicEditIndex.value = idx;
  musicEditValue.value = musicArray.value[idx] || '';
  nextTick(() => {
    if (musicEditInput.value) musicEditInput.value.focus();
  });
};

const commitMusicEdit = () => {
  const idx = musicEditIndex.value;
  if (idx < 0) return;
  const v = (musicEditValue.value || '').trim();
  if (v === '' || isQid(v)) {
    musicArray.value.splice(idx, 1);
  } else {
    musicArray.value.splice(idx, 1, v);
  }
  musicEditIndex.value = -1;
  musicEditValue.value = '';
};

const onMusicEditKeydown = (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    commitMusicEdit();
  } else if (e.key === 'Escape') {
    musicEditIndex.value = -1;
    musicEditValue.value = '';
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
  if (v === '' || isQid(v)) {
    // remove token if empty or it's a QID
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
  if (v === '' || isQid(v)) {
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
  max-width: 1100px;
  /* increased overall modal width */
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
  min-width: 260px;
  /* increased to give more room and keep cover visually balanced */
}

.dvd-cover {
  width: 260px;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  object-fit: cover;
}

.dvd-form {
  display: grid;
  grid-template-columns: 260px 1fr;
  /* left column for cover, right for fields */
  gap: 16px 64px;
  /* smaller vertical gap, large horizontal gap between cover and fields */
}

.right-column {
  /* ensure the right-side container sits in the right grid column and aligns with the cover */
  grid-column: 2;
  align-self: start;
}

.form-row {
  grid-column: 2;
  /* place paired fields in the right column */
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 6px;
}

.form-group {
  display: flex;
  flex-direction: column;
  grid-column: 2;
  /* default: place in right column */
}

.form-group.full-width {
  grid-column: 1 / -1;
  /* span both columns (under the cover) */
}

.form-row .form-group {
  grid-column: auto;
  /* allow children inside .form-row to flow into the two columns */
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
  min-width: 0;
  /* allow flexing in constrained containers */
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
  grid-column: 1 / -1;
  /* span both columns so buttons sit to the right under the form */
  padding-top: 20px;
  border-top: 1px solid #e9ecef;
}

.btn-primary,
.btn-secondary {
  padding: 6px 12px;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  font-size: .8em;
  font-weight: 600;
  transition: all 0.3s;
  min-width: 50px;
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
  margin-right: auto;
  /* push following buttons to the right */
}

.btn-delete:hover {
  background-color: #c53030;
}

/* Visual styles for disabled/aria-disabled buttons to make them appear inactive */
.btn-primary[disabled],
.btn-secondary[disabled],
.btn-delete[disabled],
.btn-primary[aria-disabled="true"],
.btn-secondary[aria-disabled="true"],
.btn-delete[aria-disabled="true"] {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
  filter: grayscale(20%);
  pointer-events: none;
  /* ensure hover/clicks don't trigger when aria-disabled used */
}

/* Ensure hover rules don't apply when disabled */
.btn-primary[disabled]:hover,
.btn-secondary[disabled]:hover,
.btn-delete[disabled]:hover,
.btn-primary[aria-disabled="true"]:hover,
.btn-secondary[aria-disabled="true"]:hover,
.btn-delete[aria-disabled="true"]:hover {
  transform: none;
  background-color: inherit;
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

/* Notes view and token styles merged into the main style block for cleanliness */
.notes-view {
  white-space: pre-wrap;
  /* preserve paragraphs/newlines */
  padding: 14px;
  /* breathing room inside the note box */
  background: #f1f7ff;
  /* slightly darker, still very light */
  border: 1px solid #d7ecff;
  /* subtle border with a bit more contrast */
  border-left: 4px solid #a9d3ff;
  /* thin left accent bar */
  border-radius: 8px;
  color: #111827;
  line-height: 1.45;
  box-shadow: 0 1px 0 rgba(16, 24, 40, 0.02) inset;
}

.plain-value {
  padding: 10px 0;
  /* align visually with inputs but without borders */
  color: #111827;
  font-size: 1rem;
  min-height: 38px;
}

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

/* Variant for token containers that should shrink to fit their contents (e.g. Genre) */
.actor-input--shrink {
  display: inline-flex;
  width: auto;
  max-width: 100%; /* don't overflow container */
  padding: 6px 8px;
  min-height: 0;
  /* Prevent parent .form-group (a column flex container) from stretching this item */
  align-self: flex-start;
}

.actor-input--shrink input {
  min-width: 60px;
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

/* Server chooser styles */
.server-chooser-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1200;
}

.server-chooser {
  background: #fff;
  padding: 16px;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  max-width: 900px;
  width: 95%;
}

.server-chooser-grid {
  display: flex;
  gap: 12px;
  align-items: start;
}

.chooser-card {
  flex: 1 1 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.chooser-img {
  max-width: 320px;
  max-height: 400px;
  width: auto;
  height: auto;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.chooser-label {
  font-size: 0.9rem;
  color: #374151;
}

.chooser-fname {
  font-size: 0.85rem;
  color: #4b5563;
  text-align: center;
  word-break: break-word;
}

/* File input + remove button inline controls inside the cover section */
.file-controls {
  margin-top: 6px;
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}
.file-controls input[type="file"] {
  /* default: allow the file control to shrink, but when inside the cover column we'll cap width */
  flex: 1 1 140px;
  min-width: 0;
  max-width: calc(100% - 110px);
}
.dvd-image-section .file-controls input[type="file"] {
  /* in the left cover column make the control compact but give more room so filename isn't obscured */
  flex: 0 0 auto;
  width: 180px;
  max-width: 180px;
}
.file-controls .btn-secondary {
  width: auto;
  min-width: 0;
  padding: 8px 12px;
}
@media (max-width: 768px) {
  .file-controls .btn-secondary { width: auto; }
}

/* right-column image actions: place Remove button and "Current:" inline, top-left aligned */
.image-actions {
  display: flex;
  /* arrange button and small text side-by-side */
  flex-direction: row;
  align-items: flex-start; /* top-align so the button sits at the top of the grid cell */
  gap: 8px;
  margin-top: 6px;
  justify-content: flex-start;
  align-self: start;
}
.image-actions small {
  display: inline-block;
  margin: 0;
  color: #4b5563;
  font-size: 0.9rem;
  line-height: 1.6;
  word-break: break-word;
}
.image-actions .btn-secondary {
  min-width: 90px;
  padding: 6px 10px;
}
</style>
