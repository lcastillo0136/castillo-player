<script setup>
import {
  computed,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  Hash,
  ListPlus,
  Loader2,
  Play,
  Search,
  Sparkles
} from 'lucide-vue-next'

import SongRow
  from '../components/SongRow.vue'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'


const {
  hashtags,
  hashtagStats,
  loadingHashtags,
  hashtagsError,
  library,

  loadHashtags,
  loadHashtagSongs,

  playFiles,
  queueAddMany
} = useCastilloApi()


const {
  route,
  navigate,
  goBack
} = useCastilloNavigation()


const searchQuery = ref('')
const detailSongsRaw = ref([])
const detailLoading = ref(false)
const detailError = ref('')
const actionBusy = ref(false)
const actionMessage = ref('')


const currentHashtag = computed(() => {
  return route.value.hashtag || ''
})


const selectedMeta = computed(() => {
  return hashtags.value.find(
    item =>
      item.name === currentHashtag.value
  ) || null
})


const filteredHashtags = computed(() => {
  const query =
    searchQuery.value
      .trim()
      .toLowerCase()

  if (!query) {
    return hashtags.value
  }

  return hashtags.value.filter(item => {
    const name =
      String(item.name || '')
        .toLowerCase()

    return name.includes(query)
  })
})


const libraryByFile = computed(() => {
  const map = new Map()

  for (
    const item
    of library.value.songs || []
  ) {
    if (item?.file) {
      map.set(
        item.file,
        item
      )
    }
  }

  return map
})


const detailSongs = computed(() => {
  return detailSongsRaw.value.map(
    item => {
      const local =
        libraryByFile.value.get(
          item.file
        ) || {}

      return {
        ...item,
        ...local,

        file:
          item.file,

        hashtag:
          item.hashtag,

        hashtag_source:
          item.hashtag_source
      }
    }
  )
})


const selectedSource = computed(() => {
  const meta =
    selectedMeta.value

  if (!meta) {
    return ''
  }

  const embedded =
    Number(
      meta.embedded_count || 0
    )

  const legacy =
    Number(
      meta.legacy_count || 0
    )

  if (
    embedded > 0 &&
    legacy > 0
  ) {
    return 'Mixto'
  }

  if (embedded > 0) {
    return 'Castillo'
  }

  return 'Heredado'
})


function sourceLabel(item) {
  const embedded =
    Number(
      item.embedded_count || 0
    )

  const legacy =
    Number(
      item.legacy_count || 0
    )

  if (
    embedded > 0 &&
    legacy > 0
  ) {
    return 'Mixto'
  }

  if (embedded > 0) {
    return 'Castillo'
  }

  return 'Heredado'
}


function sourceClass(item) {
  const label =
    sourceLabel(item)

  if (label === 'Castillo') {
    return (
      'bg-[#f2f478]/80 ' +
      'text-[#252525]'
    )
  }

  if (label === 'Mixto') {
    return (
      'bg-[#fff1e9] ' +
      'text-[#be5c2b]'
    )
  }

  return (
    'bg-black/[0.05] ' +
    'text-black/35'
  )
}


function openHashtag(name) {
  navigate(
    'hashtags',
    {
      hashtag: name
    }
  )
}


function backToHashtags() {
  /*
   * Si la navegación llegó desde el listado,
   * respetamos Atrás/Adelante del navegador.
   * Si la entrada fue directa, el botón
   * "Todos los hashtags" sigue disponible.
   */
  goBack()
}


function showAllHashtags() {
  navigate(
    'hashtags'
  )
}


async function loadDetail() {
  const name =
    currentHashtag.value

  actionMessage.value = ''

  if (!name) {
    detailSongsRaw.value = []
    detailError.value = ''
    return
  }

  detailLoading.value = true
  detailError.value = ''

  try {
    detailSongsRaw.value =
      await loadHashtagSongs(
        name
      )

  } catch (err) {
    detailSongsRaw.value = []

    detailError.value =
      err.message ||
      'No fue posible cargar el hashtag.'

  } finally {
    detailLoading.value = false
  }
}


async function playAllHashtag() {
  if (
    actionBusy.value ||
    !detailSongs.value.length
  ) {
    return
  }

  actionBusy.value = true
  actionMessage.value = ''

  try {
    await playFiles(
      detailSongs.value
    )

    actionMessage.value =
      'Reproduciendo #' +
      currentHashtag.value

  } catch (err) {
    detailError.value =
      err.message ||
      'No fue posible reproducir el hashtag.'

  } finally {
    actionBusy.value = false
  }
}


async function queueAllHashtag() {
  if (
    actionBusy.value ||
    !detailSongs.value.length
  ) {
    return
  }

  actionBusy.value = true
  actionMessage.value = ''

  try {
    await queueAddMany(
      detailSongs.value
    )

    actionMessage.value =
      `${detailSongs.value.length} canciones añadidas a la cola.`

  } catch (err) {
    detailError.value =
      err.message ||
      'No fue posible añadir las canciones a la cola.'

  } finally {
    actionBusy.value = false
  }
}


watch(
  currentHashtag,
  loadDetail,
  {
    immediate: true
  }
)


watch(
  () => route.value.view,

  async view => {
    if (view !== 'hashtags') {
      return
    }

    try {
      await loadHashtags(
        true
      )
    } catch {
      /*
       * hashtagsError ya contiene
       * el mensaje que mostramos.
       */
    }
  },

  {
    immediate: true
  }
)
</script>


<template>
  <section>
    <!-- LISTADO -->
    <template
      v-if="!currentHashtag"
    >
      <div
        class="rounded-[28px]
               bg-[#f0ede9]
               px-5 py-6
               sm:px-7 sm:py-7"
      >
        <div
          class="flex
                 flex-col gap-5
                 md:flex-row
                 md:items-end
                 md:justify-between"
        >
          <div>
            <div
              class="flex
                     items-center
                     gap-3"
            >
              <div
                class="flex h-11 w-11
                       items-center
                       justify-center
                       rounded-2xl
                       bg-[#252525]
                       text-[#f2f478]"
              >
                <Hash
                  class="h-5 w-5"
                  :stroke-width="2.4"
                />
              </div>

              <div>
                <p
                  class="text-[9px]
                         font-semibold
                         uppercase
                         tracking-[0.2em]
                         text-[#be5c2b]"
                >
                  Organización
                </p>

                <h1
                  class="text-2xl
                         font-semibold
                         sm:text-3xl"
                >
                  Hashtags
                </h1>
              </div>
            </div>

            <p
              class="mt-4
                     max-w-2xl
                     text-sm
                     leading-relaxed
                     text-black/45"
            >
              Filtra y reproduce tu biblioteca usando
              etiquetas que viajan con los archivos de audio.
            </p>
          </div>

          <div
            class="grid grid-cols-2
                   gap-2
                   sm:flex"
          >
            <div
              class="rounded-2xl
                     bg-white/70
                     px-4 py-3"
            >
              <p
                class="text-[9px]
                       uppercase
                       tracking-[0.14em]
                       text-black/30"
              >
                Hashtags
              </p>

              <p
                class="mt-1
                       text-lg
                       font-semibold"
              >
                {{
                  hashtagStats.hashtags || 0
                }}
              </p>
            </div>

            <div
              class="rounded-2xl
                     bg-white/70
                     px-4 py-3"
            >
              <p
                class="text-[9px]
                       uppercase
                       tracking-[0.14em]
                       text-black/30"
              >
                Canciones
              </p>

              <p
                class="mt-1
                       text-lg
                       font-semibold"
              >
                {{
                  hashtagStats.songs_with_hashtags || 0
                }}
              </p>
            </div>
          </div>
        </div>
      </div>


      <div
        class="mt-6
               flex flex-col
               gap-3
               sm:flex-row
               sm:items-center
               sm:justify-between"
      >
        <div
          class="relative
                 w-full
                 sm:max-w-md"
        >
          <Search
            class="pointer-events-none
                   absolute left-4
                   top-1/2
                   h-4 w-4
                   -translate-y-1/2
                   text-black/25"
          />

          <input
            v-model="searchQuery"
            type="search"
            autocomplete="off"
            placeholder="Buscar hashtag..."
            class="w-full
                   rounded-2xl
                   border
                   border-black/[0.06]
                   bg-white
                   py-3
                   pl-11 pr-4
                   text-sm
                   outline-none
                   ring-[#be5c2b]/15
                   focus:ring-2"
          />
        </div>

        <p
          class="text-xs
                 text-black/35"
        >
          {{
            filteredHashtags.length
          }}
          resultados
        </p>
      </div>


      <div
        v-if="loadingHashtags"
        class="flex
               min-h-[260px]
               items-center
               justify-center"
      >
        <Loader2
          class="h-6 w-6
                 animate-spin
                 text-[#be5c2b]"
        />
      </div>


      <div
        v-else-if="hashtagsError"
        class="mt-6
               rounded-2xl
               border
               border-red-200
               bg-red-50
               px-5 py-4
               text-sm
               text-red-700"
      >
        {{ hashtagsError }}
      </div>


      <div
        v-else-if="
          filteredHashtags.length
        "
        class="mt-5
               grid gap-3
               sm:grid-cols-2
               xl:grid-cols-3"
      >
        <button
          v-for="item in filteredHashtags"
          :key="item.name"
          type="button"
          class="group
                 flex min-w-0
                 items-center
                 gap-4
                 rounded-[22px]
                 border
                 border-black/[0.05]
                 bg-white
                 p-4
                 text-left
                 transition
                 hover:-translate-y-0.5
                 hover:shadow-lg
                 hover:shadow-black/[0.04]"
          @click="
            openHashtag(
              item.name
            )
          "
        >
          <div
            class="flex h-12 w-12
                   shrink-0
                   items-center
                   justify-center
                   rounded-2xl
                   bg-[#252525]
                   text-[#f2f478]
                   transition
                   group-hover:bg-[#be5c2b]
                   group-hover:text-white"
          >
            <Hash
              class="h-5 w-5"
              :stroke-width="2.4"
            />
          </div>

          <div
            class="min-w-0
                   flex-1"
          >
            <p
              class="truncate
                     text-sm
                     font-semibold"
            >
              #{{ item.name }}
            </p>

            <div
              class="mt-1.5
                     flex
                     items-center
                     gap-2"
            >
              <span
                class="text-[11px]
                       text-black/35"
              >
                {{ item.count }}
                {{
                  Number(item.count) === 1
                    ? 'canción'
                    : 'canciones'
                }}
              </span>

              <span
                class="rounded-full
                       px-2 py-0.5
                       text-[9px]
                       font-semibold"
                :class="
                  sourceClass(
                    item
                  )
                "
              >
                {{
                  sourceLabel(
                    item
                  )
                }}
              </span>
            </div>
          </div>
        </button>
      </div>


      <div
        v-else
        class="mt-8
               rounded-[24px]
               border
               border-dashed
               border-black/10
               bg-white/50
               px-6 py-12
               text-center"
      >
        <Hash
          class="mx-auto
                 h-8 w-8
                 text-black/15"
        />

        <p
          class="mt-3
                 text-sm
                 font-medium"
        >
          No encontramos hashtags.
        </p>

        <p
          class="mt-1
                 text-xs
                 text-black/35"
        >
          Prueba con otro término.
        </p>
      </div>
    </template>


    <!-- DETALLE -->
    <template
      v-else
    >
      <div
        class="flex
               items-center
               justify-between
               gap-3"
      >
        <button
          type="button"
          class="inline-flex
                 items-center
                 gap-2
                 rounded-full
                 px-3 py-2
                 text-xs
                 font-medium
                 text-black/45
                 transition
                 hover:bg-black/[0.04]
                 hover:text-black/70"
          @click="backToHashtags"
        >
          <ArrowLeft
            class="h-4 w-4"
          />

          Atrás
        </button>

        <button
          type="button"
          class="text-xs
                 text-[#be5c2b]
                 hover:underline"
          @click="showAllHashtags"
        >
          Todos los hashtags
        </button>
      </div>


      <div
        class="mt-4
               rounded-[28px]
               bg-[#252525]
               p-6
               text-white
               sm:p-8"
      >
        <div
          class="flex
                 flex-col gap-6
                 lg:flex-row
                 lg:items-end
                 lg:justify-between"
        >
          <div
            class="min-w-0"
          >
            <div
              class="flex
                     items-center
                     gap-3"
            >
              <div
                class="flex h-12 w-12
                       shrink-0
                       items-center
                       justify-center
                       rounded-2xl
                       bg-[#f2f478]
                       text-[#252525]"
              >
                <Hash
                  class="h-6 w-6"
                  :stroke-width="2.4"
                />
              </div>

              <div
                class="min-w-0"
              >
                <p
                  class="text-[9px]
                         font-semibold
                         uppercase
                         tracking-[0.2em]
                         text-[#f2f478]/70"
                >
                  Hashtag
                </p>

                <h1
                  class="truncate
                         text-2xl
                         font-semibold
                         sm:text-4xl"
                >
                  #{{ currentHashtag }}
                </h1>
              </div>
            </div>

            <div
              class="mt-4
                     flex
                     flex-wrap
                     items-center
                     gap-2"
            >
              <span
                class="rounded-full
                       bg-white/10
                       px-3 py-1
                       text-xs
                       text-white/65"
              >
                {{
                  detailSongs.length
                }}
                {{
                  detailSongs.length === 1
                    ? 'canción'
                    : 'canciones'
                }}
              </span>

              <span
                v-if="selectedSource"
                class="rounded-full
                       bg-[#f2f478]/10
                       px-3 py-1
                       text-xs
                       text-[#f2f478]"
              >
                {{ selectedSource }}
              </span>
            </div>
          </div>


          <div
            class="flex
                   flex-wrap
                   gap-2"
          >
            <button
              type="button"
              class="inline-flex
                     items-center
                     gap-2
                     rounded-2xl
                     bg-[#f2f478]
                     px-4 py-3
                     text-xs
                     font-semibold
                     text-[#252525]
                     transition
                     hover:brightness-95
                     disabled:opacity-40"
              :disabled="
                actionBusy ||
                !detailSongs.length
              "
              @click="playAllHashtag"
            >
              <Loader2
                v-if="actionBusy"
                class="h-4 w-4
                       animate-spin"
              />

              <Play
                v-else
                class="h-4 w-4"
                fill="currentColor"
              />

              Reproducir todo
            </button>

            <button
              type="button"
              class="inline-flex
                     items-center
                     gap-2
                     rounded-2xl
                     bg-white/10
                     px-4 py-3
                     text-xs
                     font-semibold
                     text-white
                     transition
                     hover:bg-white/15
                     disabled:opacity-40"
              :disabled="
                actionBusy ||
                !detailSongs.length
              "
              @click="queueAllHashtag"
            >
              <ListPlus
                class="h-4 w-4"
              />

              Añadir a cola
            </button>
          </div>
        </div>
      </div>


      <div
        v-if="actionMessage"
        class="mt-4
               flex items-center
               gap-2
               rounded-2xl
               bg-[#f2f478]/30
               px-4 py-3
               text-xs
               text-black/60"
      >
        <Sparkles
          class="h-4 w-4
                 text-[#be5c2b]"
        />

        {{ actionMessage }}
      </div>


      <div
        v-if="detailError"
        class="mt-4
               rounded-2xl
               border
               border-red-200
               bg-red-50
               px-5 py-4
               text-sm
               text-red-700"
      >
        {{ detailError }}
      </div>


      <div
        v-if="detailLoading"
        class="flex
               min-h-[260px]
               items-center
               justify-center"
      >
        <Loader2
          class="h-6 w-6
                 animate-spin
                 text-[#be5c2b]"
        />
      </div>


      <div
        v-else-if="
          detailSongs.length
        "
        class="mt-6
               rounded-[24px]
               border
               border-black/[0.05]
               bg-white
               p-2
               sm:p-3"
      >
        <SongRow
          v-for="item in detailSongs"
          :key="item.file"
          :song="item"
        />
      </div>


      <div
        v-else-if="!detailError"
        class="mt-6
               rounded-[24px]
               border
               border-dashed
               border-black/10
               bg-white/50
               px-6 py-12
               text-center"
      >
        <Hash
          class="mx-auto
                 h-8 w-8
                 text-black/15"
        />

        <p
          class="mt-3
                 text-sm
                 font-medium"
        >
          Este hashtag ya no contiene canciones.
        </p>
      </div>
    </template>
  </section>
</template>
