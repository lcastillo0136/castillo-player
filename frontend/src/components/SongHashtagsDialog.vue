<script setup>
import {
  computed,
  onBeforeUnmount,
  ref,
  watch
} from 'vue'

import {
  Check,
  Hash,
  Loader2,
  Plus,
  Save,
  X
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'


const props = defineProps({
  open: {
    type: Boolean,
    default: false
  },

  song: {
    type: Object,
    default: () => ({})
  }
})


const emit = defineEmits([
  'close',
  'saved'
])


const {
  coverUrl,
  applyTagOverride,
  loadRealFileTags,
  loadHashtags
} = useCastilloApi()


const loading = ref(false)
const saving = ref(false)

const error = ref('')
const warning = ref('')
const saved = ref(false)

const hashtagInput = ref('')

const sourceTags = ref({})
const embeddedHashtags = ref([])
const legacyHashtags = ref([])

let savedTimer = null


const file = computed(() => {
  return (
    props.song?.file ||
    ''
  )
})


const title = computed(() => {
  return (
    props.song?.title ||
    file.value.split('/').pop() ||
    'Canción'
  )
})


const artist = computed(() => {
  return (
    props.song?.artist ||
    'Artista desconocido'
  )
})


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


function unique(values) {
  const result = []
  const seen = new Set()

  for (const value of values) {
    const tag =
      normalizeHashtag(
        value
      )

    if (
      !tag ||
      seen.has(tag)
    ) {
      continue
    }

    seen.add(tag)
    result.push(tag)
  }

  return result
}


function addHashtags() {
  const candidates = String(
    hashtagInput.value || ''
  ).split(/[\s,;]+/)

  const current =
    unique(
      embeddedHashtags.value
    )

  const seen =
    new Set(current)

  for (const candidate of candidates) {
    const tag =
      normalizeHashtag(
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

  embeddedHashtags.value =
    current

  hashtagInput.value = ''
}


function removeHashtag(tag) {
  embeddedHashtags.value =
    embeddedHashtags.value.filter(
      item =>
        item !== tag
    )
}


function promoteLegacy(tag) {
  const normalized =
    normalizeHashtag(tag)

  if (!normalized) {
    return
  }

  if (
    embeddedHashtags.value.includes(
      normalized
    )
  ) {
    return
  }

  if (
    embeddedHashtags.value.length >= 50
  ) {
    warning.value =
      'La canción ya tiene el máximo de 50 hashtags Castillo.'
    return
  }

  embeddedHashtags.value = [
    ...embeddedHashtags.value,
    normalized
  ]
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


async function readJson(response) {
  try {
    return await response.json()
  } catch {
    return {}
  }
}


async function loadData() {
  if (
    !props.open ||
    !file.value
  ) {
    return
  }

  loading.value = true
  error.value = ''
  warning.value = ''
  saved.value = false
  hashtagInput.value = ''

  try {
    const params =
      new URLSearchParams({
        file: file.value
      })

    const [
      tagsResponse,
      indexedResponse
    ] = await Promise.all([
      fetch(
        '/castillo-api/tags.php?' +
        params.toString(),
        {
          cache: 'no-store'
        }
      ),

      fetch(
        '/castillo-api/hashtags.php?' +
        params.toString(),
        {
          cache: 'no-store'
        }
      )
    ])

    const tagsData =
      await readJson(
        tagsResponse
      )

    const indexedData =
      await readJson(
        indexedResponse
      )

    if (!tagsResponse.ok) {
      throw new Error(
        tagsData.error ||
        'No fue posible leer los tags de la canción.'
      )
    }

    if (!indexedResponse.ok) {
      throw new Error(
        indexedData.error ||
        'No fue posible leer el índice de hashtags.'
      )
    }

    sourceTags.value = {
      ...(tagsData.tags || {})
    }

    embeddedHashtags.value =
      unique(
        Array.isArray(
          tagsData.tags?.hashtags
        )
          ? tagsData.tags.hashtags
          : []
      )

    const embeddedSet =
      new Set(
        embeddedHashtags.value
      )

    legacyHashtags.value =
      unique(
        (
          indexedData.hashtags ||
          []
        )
          .filter(
            item =>
              item?.source === 'legacy'
          )
          .map(
            item =>
              item?.name || ''
          )
      )
        .filter(
          tag =>
            !embeddedSet.has(tag)
        )

  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible cargar los hashtags.'

  } finally {
    loading.value = false
  }
}


async function save() {
  if (
    saving.value ||
    !file.value
  ) {
    return
  }

  if (
    hashtagInput.value.trim()
  ) {
    addHashtags()
  }

  saving.value = true
  error.value = ''
  warning.value = ''
  saved.value = false

  try {
    const tags = {
      ...sourceTags.value,
      hashtags:
        unique(
          embeddedHashtags.value
        )
    }

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
              file: file.value,
              tags
            })
        }
      )

    const data =
      await readJson(
        response
      )

    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible guardar los hashtags.'
      )
    }

    sourceTags.value = {
      ...(data.tags || tags)
    }

    embeddedHashtags.value =
      unique(
        Array.isArray(
          data.tags?.hashtags
        )
          ? data.tags.hashtags
          : tags.hashtags
      )

    applyTagOverride(
      file.value,
      data.tags || tags
    )

    await Promise.all([
      loadRealFileTags(
        file.value,
        true
      ),

      loadHashtags(
        true
      ).catch(
        () => []
      )
    ])

    if (
      data.hashtags_index &&
      data.hashtags_index.ok === false
    ) {
      warning.value =
        'Los hashtags se guardaron dentro del audio, ' +
        'pero el índice no confirmó la actualización: ' +
        (
          data.hashtags_index.error ||
          'error desconocido'
        )
    }

    saved.value = true

    emit(
      'saved',
      {
        file: file.value,
        hashtags: [
          ...embeddedHashtags.value
        ]
      }
    )

    if (savedTimer) {
      window.clearTimeout(
        savedTimer
      )
    }

    savedTimer =
      window.setTimeout(
        () => {
          saved.value = false
        },
        1800
      )

  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible guardar los hashtags.'

  } finally {
    saving.value = false
  }
}


function close() {
  if (saving.value) {
    return
  }

  emit('close')
}


function onWindowKeydown(event) {
  if (
    event.key === 'Escape' &&
    props.open
  ) {
    close()
  }
}


watch(
  () => props.open,
  value => {
    if (value) {
      loadData()
    }
  }
)


watch(
  file,
  () => {
    if (props.open) {
      loadData()
    }
  }
)


window.addEventListener(
  'keydown',
  onWindowKeydown
)


onBeforeUnmount(
  () => {
    window.removeEventListener(
      'keydown',
      onWindowKeydown
    )

    if (savedTimer) {
      window.clearTimeout(
        savedTimer
      )
    }
  }
)
</script>


<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0
             z-[1200]
             flex items-end
             justify-center
             bg-black/35
             p-0
             backdrop-blur-[2px]
             sm:items-center
             sm:p-5"
      @mousedown.self="close"
    >
      <section
        class="w-full
               max-w-xl
               overflow-hidden
               rounded-t-[30px]
               bg-[#faf9f7]
               shadow-2xl
               sm:rounded-[30px]"
        @mousedown.stop
      >
        <header
          class="flex items-center
                 gap-3
                 border-b
                 border-black/5
                 px-5 py-4"
        >
          <div
            class="flex h-11 w-11
                   shrink-0
                   items-center
                   justify-center
                   overflow-hidden
                   rounded-[14px]
                   bg-[#ece8e4]"
          >
            <img
              v-if="file"
              :src="coverUrl(file)"
              class="h-full w-full
                     object-cover"
              alt=""
            />
          </div>


          <div class="min-w-0 flex-1">
            <p
              class="text-[9px]
                     font-semibold
                     uppercase
                     tracking-[0.16em]
                     text-[#be5c2b]"
            >
              Organización
            </p>

            <h3
              class="truncate
                     text-sm
                     font-semibold"
            >
              {{ title }}
            </h3>

            <p
              class="truncate
                     text-[11px]
                     text-black/35"
            >
              {{ artist }}
            </p>
          </div>


          <button
            type="button"
            class="flex h-9 w-9
                   items-center
                   justify-center
                   rounded-full
                   text-black/35
                   transition
                   hover:bg-black/5"
            aria-label="Cerrar"
            @click="close"
          >
            <X
              class="h-5 w-5"
            />
          </button>
        </header>


        <div
          class="max-h-[72vh]
                 overflow-y-auto
                 px-5 py-5"
        >
          <div
            v-if="loading"
            class="flex
                   min-h-[220px]
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
            <section>
              <div
                class="flex
                       items-center
                       justify-between
                       gap-3"
              >
                <div>
                  <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.14em]
                           text-black/35"
                  >
                    Hashtags Castillo
                  </p>

                  <p
                    class="mt-1
                           text-[11px]
                           text-black/30"
                  >
                    Se guardan dentro del archivo de audio.
                  </p>
                </div>

                <span
                  class="rounded-full
                         bg-[#f2f478]/70
                         px-2.5 py-1
                         text-[10px]
                         font-semibold"
                >
                  {{ embeddedHashtags.length }}/50
                </span>
              </div>


              <div
                v-if="embeddedHashtags.length"
                class="mt-4
                       flex flex-wrap
                       gap-2"
              >
                <span
                  v-for="tag in embeddedHashtags"
                  :key="tag"
                  class="inline-flex
                         items-center
                         gap-1.5
                         rounded-full
                         bg-white
                         px-3 py-2
                         text-xs
                         font-semibold
                         shadow-sm"
                >
                  <span
                    class="text-[#be5c2b]"
                  >
                    #
                  </span>

                  {{ tag }}

                  <button
                    type="button"
                    class="ml-0.5
                           flex h-5 w-5
                           items-center
                           justify-center
                           rounded-full
                           text-black/25
                           transition
                           hover:bg-red-50
                           hover:text-red-500"
                    :aria-label="`Quitar #${tag}`"
                    @click="removeHashtag(tag)"
                  >
                    <X
                      class="h-3 w-3"
                    />
                  </button>
                </span>
              </div>


              <p
                v-else
                class="mt-4
                       rounded-[16px]
                       bg-black/[0.025]
                       px-4 py-4
                       text-xs
                       text-black/35"
              >
                Esta canción todavía no tiene hashtags Castillo.
              </p>


              <div
                class="mt-4
                       flex gap-2"
              >
                <div
                  class="relative
                         min-w-0
                         flex-1"
                >
                  <Hash
                    class="pointer-events-none
                           absolute
                           left-3 top-1/2
                           h-4 w-4
                           -translate-y-1/2
                           text-[#be5c2b]"
                  />

                  <input
                    v-model="hashtagInput"
                    type="text"
                    autocomplete="off"
                    placeholder="anime, metal, favoritas..."
                    class="w-full
                           rounded-[16px]
                           bg-white
                           py-3
                           pl-9 pr-4
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
                  class="flex h-[44px]
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
            </section>


            <section
              v-if="legacyHashtags.length"
              class="mt-7
                     border-t
                     border-black/5
                     pt-6"
            >
              <p
                class="text-[10px]
                       font-semibold
                       uppercase
                       tracking-[0.14em]
                       text-black/35"
              >
                Hashtags heredados
              </p>

              <p
                class="mt-1
                       text-[11px]
                       leading-relaxed
                       text-black/30"
              >
                Provienen de los TXXX antiguos. Son de solo lectura;
                puedes copiar individualmente los que quieras al formato
                Castillo.
              </p>


              <div
                class="mt-4
                       flex flex-wrap
                       gap-2"
              >
                <button
                  v-for="tag in legacyHashtags"
                  :key="tag"
                  type="button"
                  class="inline-flex
                         items-center
                         gap-1.5
                         rounded-full
                         bg-black/[0.045]
                         px-3 py-2
                         text-xs
                         font-semibold
                         text-black/45
                         transition
                         hover:bg-black/[0.075]"
                  :title="`Copiar #${tag} a Castillo`"
                  @click="promoteLegacy(tag)"
                >
                  <Plus
                    class="h-3 w-3"
                  />

                  #{{ tag }}
                </button>
              </div>
            </section>


            <p
              v-if="warning"
              class="mt-5
                     rounded-[16px]
                     bg-amber-50
                     px-4 py-3
                     text-xs
                     leading-relaxed
                     text-amber-700"
            >
              {{ warning }}
            </p>


            <p
              v-if="error"
              class="mt-5
                     rounded-[16px]
                     bg-red-50
                     px-4 py-3
                     text-xs
                     leading-relaxed
                     text-red-600"
            >
              {{ error }}
            </p>
          </template>
        </div>


        <footer
          class="flex items-center
                 justify-end
                 gap-2
                 border-t
                 border-black/5
                 bg-white/60
                 px-5 py-4"
        >
          <button
            type="button"
            class="rounded-full
                   px-4 py-2.5
                   text-xs
                   font-semibold
                   text-black/45
                   transition
                   hover:bg-black/5"
            :disabled="saving"
            @click="close"
          >
            Cancelar
          </button>

          <button
            type="button"
            class="flex
                   min-w-[120px]
                   items-center
                   justify-center
                   gap-2
                   rounded-full
                   bg-[#252525]
                   px-5 py-2.5
                   text-xs
                   font-semibold
                   text-white
                   disabled:opacity-40"
            :disabled="
              loading ||
              saving ||
              !file
            "
            @click="save"
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
        </footer>
      </section>
    </div>
  </Teleport>
</template>
