<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  Check,
  Clock3,
  Disc3,
  FileAudio2,
  FileText,
  Gauge,
  HardDrive,
  Hash,
  ImagePlus,
  Loader2,
  Music2,
  Plus,
  Save,
  Tag,
  UserRound,
  X
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'


const {
  song,
  applyTagOverride,
  loadRealFileTags
} = useCastilloApi()


const {
  route,
  goBack
} = useCastilloNavigation()


const loading = ref(false)
const saving = ref(false)

const error = ref('')
const saved = ref(false)


const form = ref({
  title: '',
  artist: '',
  album: '',
  albumartist: '',
  date: '',
  tracknumber: '',
  tracktotal: '',
  discnumber: '',
  disctotal: '',
  genre: '',
  composer: '',
  comment: '',
  bpm: '',
  hashtags: []
})


const details = ref({
  duration_seconds: 0,
  bitrate: 0,
  sample_rate: 0,
  bits_per_sample: 0,
  channels: 0,
  format: '',
  codec: '',
  codec_detail: '',
  size: 0,
  mtime_epoch: 0,
  mtime_ns: 0,
  logical_file: '',
  filename: '',
  lrc_exists: false,
  lrc_name: '',
  lrc_size: 0
})


const targetFile = computed(() => {
  return (
    route.value.file ||
    song.value?.file ||
    ''
  )
})


const artwork = ref({
  source: 'none',
  embedded: false,
  external: false,
  url: ''
})


const artworkLoading = ref(false)
const artworkSaving = ref(false)
const artworkError = ref('')

const selectedArtwork = ref(null)
const artworkPreview = ref('')
const artworkDragActive = ref(false)

const hashtagInput = ref('')


function normalizeText(value) {
  if (Array.isArray(value)) {
    return value
      .filter(Boolean)
      .map(String)
      .join(', ')
  }

  if (
    value === null ||
    value === undefined
  ) {
    return ''
  }

  return String(value)
}


function resetForm() {
  form.value = {
    title: '',
    artist: '',
    album: '',
    albumartist: '',
    date: '',
    tracknumber: '',
    tracktotal: '',
    discnumber: '',
    disctotal: '',
    genre: '',
    composer: '',
    comment: '',
    bpm: '',
    hashtags: []
  }

  hashtagInput.value = ''
}


function resetDetails() {
  details.value = {
    duration_seconds: 0,
    bitrate: 0,
    sample_rate: 0,
    bits_per_sample: 0,
    channels: 0,
    format: '',
    codec: '',
    codec_detail: '',
    size: 0,
    mtime_epoch: 0,
    mtime_ns: 0,
    logical_file: '',
    filename: '',
    lrc_exists: false,
    lrc_name: '',
    lrc_size: 0
  }
}


function clearArtworkPreview() {
  if (artworkPreview.value) {
    URL.revokeObjectURL(
      artworkPreview.value
    )
  }

  artworkPreview.value = ''
}


function resetArtworkSelection() {
  selectedArtwork.value = null
  clearArtworkPreview()
  artworkDragActive.value = false
}


async function loadTags() {
  const file =
    targetFile.value

  if (!file) {
    resetForm()
    resetDetails()
    return
  }


  loading.value = true
  error.value = ''


  try {
    const response =
      await fetch(
        '/castillo-api/tags.php?' +
        new URLSearchParams({
          file
        }),
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer los tags.'
      )
    }


    form.value = {
      title:
        data.tags?.title || '',

      artist:
        data.tags?.artist || '',

      album:
        data.tags?.album || '',

      albumartist:
        data.tags?.albumartist || '',

      date:
        data.tags?.date || '',

      tracknumber:
        data.tags?.tracknumber || '',

      tracktotal:
        data.tags?.tracktotal || '',

      discnumber:
        data.tags?.discnumber || '',

      disctotal:
        data.tags?.disctotal || '',

      genre:
        data.tags?.genre || '',

      composer:
        data.tags?.composer || '',

      comment:
        data.tags?.comment || '',

      bpm:
        data.tags?.bpm || '',

      hashtags:
        Array.isArray(data.tags?.hashtags)
          ? data.tags.hashtags
          : []
    }


    details.value = {
      ...details.value,
      ...(data.details || {})
    }


  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible leer los tags.'

  } finally {
    loading.value = false
  }
}


function normalizeHashtag(value) {
  const text = String(
    value || ''
  )
    .trim()
    .replace(/^#+/, '')
    .normalize('NFKD')
    .replace(/\p{M}+/gu, '')
    .toLowerCase()


  return text
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 64)
}


function addHashtags() {
  const candidates = String(
    hashtagInput.value || ''
  ).split(/[\s,;]+/)


  const current = Array.isArray(
    form.value.hashtags
  )
    ? [...form.value.hashtags]
    : []


  const seen = new Set(
    current
  )


  for (const candidate of candidates) {
    const tag = normalizeHashtag(
      candidate
    )


    if (
      !tag ||
      seen.has(tag) ||
      current.length >= 50
    ) {
      continue
    }


    seen.add(tag)
    current.push(tag)
  }


  form.value.hashtags =
    current

  hashtagInput.value = ''
}


function removeHashtag(tag) {
  form.value.hashtags =
    (form.value.hashtags || [])
      .filter(
        item =>
          item !== tag
      )
}


function onHashtagKeydown(event) {
  if (
    event.key === 'Enter' ||
    event.key === ',' ||
    event.key === ';'
  ) {
    event.preventDefault()
    addHashtags()
  }
}


async function saveTags() {
  const file =
    targetFile.value

  if (
    !file ||
    saving.value
  ) {
    return
  }


  /*
   * Si quedó texto escrito pero el usuario no
   * pulsó Enter, también lo incorporamos.
   */
  if (hashtagInput.value.trim()) {
    addHashtags()
  }


  saving.value = true
  saved.value = false
  error.value = ''


  try {
    const response =
      await fetch(
        '/castillo-api/tags.php',
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify({
              file,

              tags: {
                ...form.value
              }
            })
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible guardar los tags.'
      )
    }


    form.value = {
      ...form.value,
      ...(data.tags || {})
    }


    details.value = {
      ...details.value,
      ...(data.details || {})
    }


    /*
     * Actualizamos inmediatamente Castillo aunque
     * la cola MPD aún conserve tags anteriores.
     */
    applyTagOverride(
      file,
      data.tags || {}
    )


    await loadRealFileTags(
      file,
      true
    )


    saved.value = true


    window.setTimeout(
      () => {
        saved.value = false
      },
      1800
    )


  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible guardar los tags.'

  } finally {
    saving.value = false
  }
}


async function loadArtwork() {
  const file =
    targetFile.value

  if (!file) {
    return
  }


  artworkLoading.value = true
  artworkError.value = ''


  try {
    const response =
      await fetch(
        '/castillo-api/artwork.php?' +
        new URLSearchParams({
          file
        }),
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer la portada.'
      )
    }


    artwork.value = {
      source:
        data.source || 'none',

      embedded:
        Boolean(data.embedded),

      external:
        Boolean(data.external),

      url:
        data.url || ''
    }


  } catch (exception) {
    artworkError.value =
      exception.message ||
      'No fue posible leer la portada.'

  } finally {
    artworkLoading.value = false
  }
}


async function saveArtwork() {
  const file =
    targetFile.value


  if (
    !file ||
    !selectedArtwork.value ||
    artworkSaving.value
  ) {
    return
  }


  artworkSaving.value = true
  artworkError.value = ''


  try {
    const body =
      new FormData()


    body.append(
      'file',
      file
    )


    body.append(
      'image',
      selectedArtwork.value
    )


    const response =
      await fetch(
        '/castillo-api/artwork.php',
        {
          method: 'POST',
          body
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible guardar la portada.'
      )
    }


    artwork.value = {
      source:
        data.source || 'embedded',

      embedded:
        Boolean(data.embedded),

      external:
        Boolean(data.external),

      url:
        data.url || ''
    }


    resetArtworkSelection()


  } catch (exception) {
    artworkError.value =
      exception.message ||
      'No fue posible guardar la portada.'

  } finally {
    artworkSaving.value = false
  }
}


async function removeArtwork() {
  const file =
    targetFile.value


  if (
    !file ||
    artworkSaving.value
  ) {
    return
  }


  artworkSaving.value = true
  artworkError.value = ''


  try {
    const response =
      await fetch(
        '/castillo-api/artwork.php',
        {
          method: 'DELETE',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify({
              file
            })
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible eliminar la portada.'
      )
    }


    artwork.value = {
      source:
        data.source || 'none',

      embedded:
        Boolean(data.embedded),

      external:
        Boolean(data.external),

      url:
        data.url || ''
    }


  } catch (exception) {
    artworkError.value =
      exception.message ||
      'No fue posible eliminar la portada.'

  } finally {
    artworkSaving.value = false
  }
}


function setArtworkFile(file) {
  if (!file) {
    return false
  }


  if (
    ![
      'image/jpeg',
      'image/png'
    ].includes(
      file.type
    )
  ) {
    artworkError.value =
      'Selecciona una imagen JPEG o PNG.'

    return false
  }


  if (
    file.size >
    10 * 1024 * 1024
  ) {
    artworkError.value =
      'La imagen no puede superar 10 MB.'

    return false
  }


  selectedArtwork.value =
    file


  clearArtworkPreview()


  artworkPreview.value =
    URL.createObjectURL(
      file
    )


  artworkError.value = ''

  return true
}


function selectArtwork(event) {
  const file =
    event.target.files?.[0]


  if (!file) {
    return
  }


  setArtworkFile(
    file
  )


  event.target.value = ''
}


function dropArtwork(event) {
  artworkDragActive.value = false


  const files =
    Array.from(
      event.dataTransfer?.files || []
    )


  const file =
    files.find(
      item =>
        [
          'image/jpeg',
          'image/png'
        ].includes(
          item.type
        )
    ) || files[0]


  if (!file) {
    artworkError.value =
      'No se encontró una imagen para usar como portada.'

    return
  }


  setArtworkFile(
    file
  )
}


function formatDuration(seconds) {
  const total =
    Math.max(
      0,
      Math.round(
        Number(seconds) || 0
      )
    )


  const hours =
    Math.floor(
      total / 3600
    )

  const minutes =
    Math.floor(
      (total % 3600) / 60
    )

  const secs =
    total % 60


  if (hours > 0) {
    return (
      `${hours}:` +
      `${String(minutes).padStart(2, '0')}:` +
      String(secs).padStart(2, '0')
    )
  }


  return (
    `${minutes}:` +
    String(secs).padStart(2, '0')
  )
}


function formatBytes(bytes) {
  let value =
    Number(bytes) || 0


  if (value <= 0) {
    return '—'
  }


  const units = [
    'B',
    'KB',
    'MB',
    'GB'
  ]

  let unit = 0


  while (
    value >= 1024 &&
    unit < units.length - 1
  ) {
    value /= 1024
    unit++
  }


  return (
    `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
  )
}


function formatBitrate(value) {
  const bitrate =
    Number(value) || 0


  if (!bitrate) {
    return '—'
  }


  return (
    `${Math.round(bitrate / 1000)} kbps`
  )
}


function formatSampleRate(value) {
  const rate =
    Number(value) || 0


  if (!rate) {
    return '—'
  }


  const khz =
    rate / 1000


  return (
    `${Number.isInteger(khz) ? khz : khz.toFixed(1)} kHz`
  )
}


function formatChannels(value) {
  const channels =
    Number(value) || 0


  if (channels === 1) {
    return 'Mono'
  }


  if (channels === 2) {
    return 'Stereo'
  }


  if (channels > 0) {
    return `${channels} canales`
  }


  return '—'
}


function formatModified(epoch) {
  const value =
    Number(epoch) || 0


  if (!value) {
    return '—'
  }


  return new Intl.DateTimeFormat(
    'es-MX',
    {
      dateStyle: 'medium',
      timeStyle: 'short'
    }
  ).format(
    new Date(
      value * 1000
    )
  )
}


async function reloadCurrentFile() {
  resetArtworkSelection()
  await Promise.all([
    loadTags(),
    loadArtwork()
  ])
}


watch(
  targetFile,
  () => {
    reloadCurrentFile()
  }
)


onMounted(
  () => {
    reloadCurrentFile()
  }
)


onBeforeUnmount(
  () => {
    clearArtworkPreview()
  }
)
</script>


<template>
  <div
    class="mx-auto
           min-h-full
           max-w-4xl
           pb-16
           pt-[76px]
           lg:pt-0"
  >
    <!-- MOBILE HEADER -->
    <header
      class="fixed
             inset-x-0
             top-0
             z-[300]
             border-b
             border-black/5
             bg-[#faf9f7]/95
             backdrop-blur-xl
             lg:hidden"
    >
      <div
        class="grid
               min-h-[64px]
               grid-cols-[44px_1fr_44px]
               items-center
               px-5"
      >
        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-black/[0.035]"
          @click="goBack"
        >
          <ArrowLeft
            class="h-5 w-5"
          />
        </button>


        <div class="text-center">
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Castillo
          </p>

          <p
            class="text-[13px]
                   font-semibold"
          >
            Editor de tags
          </p>
        </div>


        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-[#252525]
                 text-white
                 disabled:opacity-40"
          :disabled="
            saving ||
            loading
          "
          @click="saveTags"
        >
          <Loader2
            v-if="saving"
            class="h-4 w-4
                   animate-spin"
          />

          <Check
            v-else-if="saved"
            class="h-4 w-4
                   text-[#f2f478]"
          />

          <Save
            v-else
            class="h-4 w-4"
          />
        </button>
      </div>
    </header>


    <!-- DESKTOP HEADER -->
    <header
      class="hidden
             items-center
             justify-between
             border-b
             border-black/5
             py-5
             lg:flex"
    >
      <button
        class="flex
               items-center
               gap-2
               rounded-full
               px-3 py-2
               text-sm
               text-black/45
               transition
               hover:bg-black/[0.04]"
        @click="goBack"
      >
        <ArrowLeft
          class="h-4 w-4"
        />

        Volver
      </button>


      <div class="text-center">
        <p
          class="text-[10px]
                 font-semibold
                 uppercase
                 tracking-[0.18em]
                 text-[#be5c2b]"
        >
          Castillo
        </p>

        <h1
          class="mt-1
                 text-sm
                 font-semibold"
        >
          Editor de tags
        </h1>
      </div>


      <button
        class="flex
               items-center
               gap-2
               rounded-full
               bg-[#252525]
               px-4 py-2
               text-xs
               font-semibold
               text-white
               disabled:opacity-40"
        :disabled="
          saving ||
          loading
        "
        @click="saveTags"
      >
        <Loader2
          v-if="saving"
          class="h-4 w-4
                 animate-spin"
        />

        <Check
          v-else-if="saved"
          class="h-4 w-4
                 text-[#f2f478]"
        />

        <Save
          v-else
          class="h-4 w-4"
        />

        {{
          saving
            ? 'Guardando…'
            : saved
              ? 'Guardado'
              : 'Guardar'
        }}
      </button>
    </header>


    <!-- SONG -->
    <section
      class="mt-7
             flex
             items-center
             gap-5
             px-4
             lg:px-0"
    >
      <div
        class="flex
               h-20 w-20
               shrink-0
               items-center
               justify-center
               rounded-[22px]
               bg-[#625d5a]
               text-[#f2f478]"
      >
        <Music2
          class="h-7 w-7"
        />
      </div>


      <div class="min-w-0">
        <h2
          class="truncate
                 text-xl
                 font-semibold"
        >
          {{
            form.title ||
            song.title ||
            'Sin título'
          }}
        </h2>

        <p
          class="mt-1
                 truncate
                 text-sm
                 text-black/40"
        >
          {{
            form.artist ||
            normalizeText(song.artist) ||
            '—'
          }}
        </p>

        <p
          class="mt-1
                 truncate
                 text-xs
                 text-black/25"
        >
          {{ targetFile }}
        </p>
      </div>
    </section>


    <!-- ERROR -->
    <div
      v-if="error"
      class="mx-4
             mt-6
             rounded-[20px]
             bg-red-50
             px-4 py-3
             text-sm
             text-red-600
             lg:mx-0"
    >
      {{ error }}
    </div>


    <!-- COVER -->
    <section
      class="relative
             mx-4
             mt-8
             overflow-hidden
             rounded-[28px]
             border-2
             bg-[#f1eeeb]
             p-5
             transition
             lg:mx-0"
      :class="
        artworkDragActive
          ? 'border-[#be5c2b] bg-[#f7eee8]'
          : 'border-transparent'
      "
      @dragenter.prevent="
        artworkDragActive = true
      "
      @dragover.prevent="
        artworkDragActive = true
      "
      @dragleave.prevent="
        artworkDragActive = false
      "
      @drop.prevent="dropArtwork"
    >
      <!-- DROP OVERLAY -->
      <div
        v-if="artworkDragActive"
        class="pointer-events-none
               absolute
               inset-0
               z-20
               flex
               items-center
               justify-center
               bg-[#faf9f7]/90
               backdrop-blur-sm"
      >
        <div class="text-center">
          <div
            class="mx-auto
                   flex
                   h-14 w-14
                   items-center
                   justify-center
                   rounded-[18px]
                   bg-[#252525]
                   text-[#f2f478]"
          >
            <ImagePlus
              class="h-6 w-6"
            />
          </div>

          <p
            class="mt-4
                   text-sm
                   font-semibold"
          >
            Suelta para usar como portada
          </p>

          <p
            class="mt-1
                   text-xs
                   text-black/35"
          >
            JPEG o PNG · máximo 10 MB
          </p>
        </div>
      </div>


      <div
        class="flex
               flex-col
               gap-6
               sm:flex-row"
      >
        <!-- COVER PREVIEW -->
        <div
          class="aspect-square
                 w-full
                 max-w-[220px]
                 shrink-0
                 overflow-hidden
                 rounded-[24px]
                 bg-[#ded9d4]"
        >
          <img
            v-if="
              artworkPreview ||
              artwork.url
            "
            :src="
              artworkPreview ||
              artwork.url
            "
            class="h-full
                   w-full
                   object-cover"
          />

          <div
            v-else
            class="flex
                   h-full
                   items-center
                   justify-center
                   text-black/20"
          >
            <Disc3
              class="h-10 w-10"
            />
          </div>
        </div>


        <!-- COVER CONTROLS -->
        <div
          class="flex
                 min-w-0
                 flex-1
                 flex-col
                 justify-center"
        >
          <p
            class="text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.16em]
                   text-black/30"
          >
            Portada
          </p>


          <p
            class="mt-2
                   text-sm
                   font-semibold"
          >
            {{
              selectedArtwork
                ? 'Nueva portada preparada'
                : artwork.source === 'embedded'
                  ? 'Portada embebida'
                  : artwork.source === 'external'
                    ? 'Portada externa'
                    : 'Sin portada'
            }}
          </p>


          <p
            class="mt-1
                   text-xs
                   leading-relaxed
                   text-black/35"
          >
            Arrastra una imagen a esta sección o
            selecciónala manualmente. La portada se
            guardará dentro del archivo de audio.
          </p>


          <p
            v-if="selectedArtwork"
            class="mt-3
                   truncate
                   text-[11px]
                   font-medium
                   text-[#be5c2b]"
          >
            {{ selectedArtwork.name }}
            ·
            {{ formatBytes(selectedArtwork.size) }}
          </p>


          <div
            class="mt-5
                   flex
                   flex-wrap
                   gap-2"
          >
            <label
              class="cursor-pointer
                     rounded-full
                     bg-white
                     px-4 py-2.5
                     text-xs
                     font-semibold
                     shadow-sm"
            >
              Seleccionar imagen

              <input
                type="file"
                accept="image/jpeg,image/png"
                class="hidden"
                @change="selectArtwork"
              />
            </label>


            <button
              v-if="selectedArtwork"
              class="rounded-full
                     bg-[#252525]
                     px-4 py-2.5
                     text-xs
                     font-semibold
                     text-white
                     disabled:opacity-40"
              :disabled="artworkSaving"
              @click="saveArtwork"
            >
              {{
                artworkSaving
                  ? 'Guardando…'
                  : 'Usar como portada'
              }}
            </button>


            <button
              v-if="selectedArtwork"
              class="rounded-full
                     px-4 py-2.5
                     text-xs
                     font-semibold
                     text-black/40
                     hover:bg-white"
              :disabled="artworkSaving"
              @click="resetArtworkSelection"
            >
              Cancelar
            </button>


            <button
              v-if="artwork.embedded"
              class="rounded-full
                     px-4 py-2.5
                     text-xs
                     font-semibold
                     text-red-500
                     transition
                     hover:bg-red-50"
              :disabled="artworkSaving"
              @click="removeArtwork"
            >
              Eliminar embebida
            </button>
          </div>


          <p
            v-if="artwork.source === 'external'"
            class="mt-4
                   text-[11px]
                   text-black/30"
          >
            Esta portada proviene de una imagen de la carpeta.
          </p>


          <p
            v-if="artworkError"
            class="mt-4
                   text-xs
                   text-red-500"
          >
            {{ artworkError }}
          </p>
        </div>
      </div>
    </section>


    <!-- LOADING -->
    <div
      v-if="loading"
      class="flex
             min-h-[300px]
             items-center
             justify-center"
    >
      <Loader2
        class="h-6 w-6
               animate-spin
               text-black/30"
      />
    </div>


    <template v-else>
      <!-- FORM -->
      <section
        class="mx-4
               mt-8
               grid
               gap-4
               rounded-[28px]
               bg-[#f1eeeb]
               p-5
               md:grid-cols-2
               lg:mx-0"
      >
        <!-- TITLE -->
        <label class="md:col-span-2">
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Título
          </span>

          <input
            v-model="form.title"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- ARTIST -->
        <label>
          <span
            class="mb-2
                   flex
                   items-center
                   gap-2
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            <UserRound
              class="h-3.5 w-3.5"
            />

            Artista
          </span>

          <input
            v-model="form.artist"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- ALBUM ARTIST -->
        <label>
          <span
            class="mb-2
                   flex
                   items-center
                   gap-2
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            <UserRound
              class="h-3.5 w-3.5"
            />

            Artista del álbum
          </span>

          <input
            v-model="form.albumartist"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- ALBUM -->
        <label>
          <span
            class="mb-2
                   flex
                   items-center
                   gap-2
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            <Disc3
              class="h-3.5 w-3.5"
            />

            Álbum
          </span>

          <input
            v-model="form.album"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- DATE -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Fecha / año
          </span>

          <input
            v-model="form.date"
            placeholder="2006 o 2006-10-03"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- TRACK -->
        <label>
          <span
            class="mb-2
                   flex
                   items-center
                   gap-2
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            <Tag
              class="h-3.5 w-3.5"
            />

            Número de pista
          </span>

          <input
            v-model="form.tracknumber"
            inputmode="numeric"
            placeholder="4"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- TRACK TOTAL -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Total de pistas
          </span>

          <input
            v-model="form.tracktotal"
            inputmode="numeric"
            placeholder="12"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- DISC -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Número de disco
          </span>

          <input
            v-model="form.discnumber"
            inputmode="numeric"
            placeholder="1"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- DISC TOTAL -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Total de discos
          </span>

          <input
            v-model="form.disctotal"
            inputmode="numeric"
            placeholder="2"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- GENRE -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Género
          </span>

          <input
            v-model="form.genre"
            placeholder="Symphonic Metal"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- COMPOSER -->
        <label>
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Compositor
          </span>

          <input
            v-model="form.composer"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- BPM -->
        <label>
          <span
            class="mb-2
                   flex
                   items-center
                   gap-2
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            <Gauge
              class="h-3.5 w-3.5"
            />

            BPM
          </span>

          <input
            v-model="form.bpm"
            inputmode="decimal"
            placeholder="120"
            class="w-full
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>


        <!-- COMMENT -->
        <label class="md:col-span-2">
          <span
            class="mb-2
                   block
                   text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-black/35"
          >
            Comentario
          </span>

          <textarea
            v-model="form.comment"
            rows="4"
            class="w-full
                   resize-y
                   rounded-[16px]
                   bg-white
                   px-4 py-3
                   text-sm
                   outline-none
                   ring-[#be5c2b]/20
                   focus:ring-2"
          />
        </label>
      </section>


      <!-- HASHTAGS -->
      <section
        class="mx-4
               mt-8
               rounded-[28px]
               bg-[#f1eeeb]
               p-5
               lg:mx-0"
      >
        <div
          class="flex
                 items-center
                 gap-3"
        >
          <div
            class="flex
                   h-10 w-10
                   shrink-0
                   items-center
                   justify-center
                   rounded-[14px]
                   bg-[#252525]
                   text-[#f2f478]"
          >
            <Hash
              class="h-4 w-4"
            />
          </div>

          <div>
            <p
              class="text-[10px]
                     font-semibold
                     uppercase
                     tracking-[0.16em]
                     text-[#be5c2b]"
            >
              Organización
            </p>

            <h3
              class="mt-0.5
                     text-base
                     font-semibold"
            >
              Hashtags
            </h3>
          </div>
        </div>


        <p
          class="mt-4
                 text-xs
                 leading-relaxed
                 text-black/40"
        >
          Se guardan dentro del archivo de audio y viajarán
          con la canción al NAS y a futuras aplicaciones de
          Castillo Player.
        </p>


        <div
          v-if="form.hashtags.length"
          class="mt-4
                 flex
                 flex-wrap
                 gap-2"
        >
          <span
            v-for="hashtag in form.hashtags"
            :key="hashtag"
            class="inline-flex
                   items-center
                   gap-1.5
                   rounded-full
                   bg-white
                   px-3 py-2
                   text-xs
                   font-semibold
                   text-[#625d5a]
                   shadow-sm"
          >
            <span
              class="text-[#be5c2b]"
            >
              #
            </span>

            {{ hashtag }}

            <button
              type="button"
              class="ml-0.5
                     flex
                     h-5 w-5
                     items-center
                     justify-center
                     rounded-full
                     text-black/25
                     transition
                     hover:bg-black/[0.06]
                     hover:text-red-500"
              :aria-label="`Eliminar #${hashtag}`"
              @click="removeHashtag(hashtag)"
            >
              <X
                class="h-3 w-3"
              />
            </button>
          </span>
        </div>


        <div
          class="mt-4
                 flex
                 gap-2"
        >
          <div
            class="relative
                   min-w-0
                   flex-1"
          >
            <span
              class="pointer-events-none
                     absolute
                     left-4
                     top-1/2
                     -translate-y-1/2
                     text-sm
                     font-semibold
                     text-[#be5c2b]"
            >
              #
            </span>

            <input
              v-model="hashtagInput"
              type="text"
              autocomplete="off"
              placeholder="anime, jrock, favoritas..."
              class="w-full
                     rounded-[16px]
                     bg-white
                     py-3
                     pl-8 pr-4
                     text-sm
                     outline-none
                     ring-[#be5c2b]/20
                     focus:ring-2"
              @keydown="onHashtagKeydown"
              @blur="addHashtags()"
            />
          </div>


          <button
            type="button"
            class="flex
                   h-[44px]
                   shrink-0
                   items-center
                   gap-2
                   rounded-[14px]
                   bg-[#252525]
                   px-4
                   text-xs
                   font-semibold
                   text-white
                   transition
                   hover:bg-[#be5c2b]"
            @mousedown.prevent
            @click="addHashtags()"
          >
            <Plus
              class="h-4 w-4"
            />

            Añadir
          </button>
        </div>


        <p
          class="mt-3
                 text-[10px]
                 text-black/30"
        >
          Puedes escribir varios separados por espacios, comas
          o punto y coma. Castillo elimina #, acentos y
          puntuación al guardarlos. Máximo 50 por canción.
        </p>
      </section>


      <!-- DETAILS -->
      <section
        class="mx-4
               mt-8
               rounded-[28px]
               bg-[#252525]
               p-5
               text-white
               lg:mx-0"
      >
        <div
          class="flex
                 items-end
                 justify-between
                 gap-4"
        >
          <div>
            <p
              class="text-[10px]
                     font-semibold
                     uppercase
                     tracking-[0.16em]
                     text-[#f2f478]"
            >
              Detalles
            </p>

            <h3
              class="mt-1
                     text-lg
                     font-semibold"
            >
              Archivo de audio
            </h3>
          </div>

          <FileAudio2
            class="h-5 w-5
                   text-white/30"
          />
        </div>


        <div
          class="mt-5
                 grid
                 gap-3
                 sm:grid-cols-2
                 lg:grid-cols-3"
        >
          <!-- DURATION -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <div
              class="flex
                     items-center
                     gap-2
                     text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              <Clock3
                class="h-3.5 w-3.5"
              />

              Duración
            </div>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ formatDuration(details.duration_seconds) }}
            </p>
          </div>


          <!-- FORMAT -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Formato / codec
            </p>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ details.format || '—' }}
            </p>

            <p
              class="mt-1
                     truncate
                     text-[10px]
                     text-white/30"
            >
              {{
                details.codec_detail ||
                details.codec ||
                '—'
              }}
            </p>
          </div>


          <!-- BITRATE -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Bitrate
            </p>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ formatBitrate(details.bitrate) }}
            </p>
          </div>


          <!-- SAMPLE RATE -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Frecuencia
            </p>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ formatSampleRate(details.sample_rate) }}
            </p>
          </div>


          <!-- BIT DEPTH -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Profundidad
            </p>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{
                details.bits_per_sample
                  ? `${details.bits_per_sample} bit`
                  : '—'
              }}
            </p>
          </div>


          <!-- CHANNELS -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Canales
            </p>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ formatChannels(details.channels) }}
            </p>
          </div>


          <!-- SIZE -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <div
              class="flex
                     items-center
                     gap-2
                     text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              <HardDrive
                class="h-3.5 w-3.5"
              />

              Tamaño
            </div>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{ formatBytes(details.size) }}
            </p>
          </div>


          <!-- LYRICS -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <div
              class="flex
                     items-center
                     gap-2
                     text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              <FileText
                class="h-3.5 w-3.5"
              />

              Letras LRC
            </div>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
              :class="
                details.lrc_exists
                  ? 'text-[#f2f478]'
                  : 'text-white/60'
              "
            >
              {{
                details.lrc_exists
                  ? 'Disponible'
                  : 'Sin archivo'
              }}
            </p>

            <p
              v-if="details.lrc_name"
              class="mt-1
                     truncate
                     text-[10px]
                     text-white/30"
            >
              {{ details.lrc_name }}
            </p>
          </div>


          <!-- ARTWORK -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <div
              class="flex
                     items-center
                     gap-2
                     text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              <ImagePlus
                class="h-3.5 w-3.5"
              />

              Portada
            </div>

            <p
              class="mt-2
                     text-sm
                     font-semibold"
            >
              {{
                artwork.embedded
                  ? 'Embebida'
                  : artwork.external
                    ? 'Externa'
                    : 'Sin portada'
              }}
            </p>
          </div>


          <!-- MODIFIED -->
          <div
            class="rounded-[18px]
                   bg-white/[0.06]
                   p-4"
          >
            <p
              class="text-[10px]
                     uppercase
                     tracking-[0.12em]
                     text-white/35"
            >
              Última modificación
            </p>

            <p
              class="mt-2
                     text-xs
                     font-medium"
            >
              {{ formatModified(details.mtime_epoch) }}
            </p>
          </div>
        </div>


        <!-- PATH -->
        <div
          class="mt-3
                 rounded-[18px]
                 bg-white/[0.06]
                 p-4"
        >
          <p
            class="text-[10px]
                   uppercase
                   tracking-[0.12em]
                   text-white/35"
          >
            Ruta
          </p>

          <p
            class="mt-2
                   break-all
                   text-[11px]
                   leading-relaxed
                   text-white/55"
          >
            {{
              details.logical_file ||
              targetFile ||
              '—'
            }}
          </p>
        </div>
      </section>
    </template>
  </div>
</template>
