<script setup>
import {
  onBeforeUnmount,
  ref,
  watch
} from 'vue'

import {
  Hash,
  ListPlus,
  Loader2,
  Play,
  Search
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
  search,
  playFiles,
  queueAddMany,
  loadHashtags,
  loadHashtagSongs
} = useCastilloApi()


const {
  navigate
} = useCastilloNavigation()


const query = ref('')

const results = ref([])
const total = ref(0)

const hashtagResults = ref([])

const searching = ref(false)

const playingResults = ref(false)
const addingResults = ref(false)

const message = ref('')
const error = ref('')


let timer = null
let searchRun = 0


function normalizeSearchText(value) {
  return String(
    value || ''
  )
    .trim()
    .replace(/^#+/, '')
    .toLowerCase()
}


function uniqueSongs(items) {
  const seen = new Set()
  const result = []

  for (const item of items) {
    const file =
      String(
        item?.file || ''
      )

    if (
      !file ||
      seen.has(file)
    ) {
      continue
    }

    seen.add(file)
    result.push(item)
  }

  return result
}


function hashtagLabel(item) {
  const embedded =
    Number(
      item?.embedded_count
    ) || 0

  const legacy =
    Number(
      item?.legacy_count
    ) || 0

  if (
    embedded > 0 &&
    legacy > 0
  ) {
    return 'Castillo + heredado'
  }

  if (embedded > 0) {
    return 'Castillo'
  }

  if (legacy > 0) {
    return 'Heredado'
  }

  return ''
}


async function performSearch(text) {
  const run =
    ++searchRun

  searching.value = true

  message.value = ''
  error.value = ''


  try {
    const cleanHashtagQuery =
      normalizeSearchText(
        text
      )

    const [
      musicResult,
      hashtags
    ] = await Promise.all([
      search(text),

      loadHashtags()
        .catch(
          () => []
        )
    ])


    if (
      run !== searchRun
    ) {
      return
    }


    const matchedHashtags =
      (
        hashtags || []
      )
        .filter(
          item => {
            const name =
              normalizeSearchText(
                item?.name
              )

            return (
              cleanHashtagQuery &&
              name.includes(
                cleanHashtagQuery
              )
            )
          }
        )
        .slice(0, 12)


    hashtagResults.value =
      matchedHashtags


    const normalSongs =
      musicResult.songs || []


    /*
     * Si existe un hashtag con el mismo
     * nombre exacto que la consulta,
     * incluimos también sus canciones.
     *
     * Ejemplos:
     *
     * metal
     * #metal
     *
     * En coincidencias parciales solo
     * mostramos el hashtag.
     */
    const exactHashtag =
      matchedHashtags.find(
        item =>
          normalizeSearchText(
            item?.name
          ) === cleanHashtagQuery
      )


    let hashtagSongs = []


    if (exactHashtag) {
      try {
        hashtagSongs =
          await loadHashtagSongs(
            exactHashtag.name
          )

      } catch {
        hashtagSongs = []
      }
    }


    if (
      run !== searchRun
    ) {
      return
    }


    /*
     * Eliminamos duplicados.
     *
     * Una canción puede haber aparecido
     * tanto en la búsqueda normal de MPD
     * como por pertenecer al hashtag.
     */
    results.value =
      uniqueSongs([
        ...normalSongs,
        ...hashtagSongs
      ])


    total.value =
      results.value.length


  } catch (exception) {
    if (
      run !== searchRun
    ) {
      return
    }

    results.value = []
    hashtagResults.value = []
    total.value = 0

    error.value =
      exception?.message ||
      'No fue posible realizar la búsqueda.'

  } finally {
    if (
      run === searchRun
    ) {
      searching.value = false
    }
  }
}


watch(
  query,

  value => {
    clearTimeout(timer)

    /*
     * Invalida cualquier búsqueda
     * anterior que todavía esté esperando
     * respuesta.
     */
    searchRun += 1

    message.value = ''
    error.value = ''

    const text =
      value.trim()


    if (!text) {
      results.value = []
      hashtagResults.value = []
      total.value = 0
      searching.value = false

      return
    }


    timer =
      setTimeout(
        () => {
          performSearch(
            text
          )
        },
        250
      )
  }
)


function openHashtag(item) {
  const name =
    String(
      item?.name || ''
    )
      .trim()
      .replace(/^#+/, '')

  if (!name) {
    return
  }


  navigate(
    'hashtags',
    {
      hashtag: name
    }
  )
}


async function playAllResults() {
  if (
    !results.value.length ||
    playingResults.value ||
    addingResults.value
  ) {
    return
  }


  playingResults.value = true

  message.value = ''
  error.value = ''


  try {
    await playFiles(
      results.value
    )


    message.value =
      results.value.length === 1
        ? 'Reproduciendo 1 resultado.'
        : `Reproduciendo ${results.value.length} resultados.`


  } catch (exception) {
    error.value =
      exception?.message ||
      'No fue posible reproducir los resultados.'

  } finally {
    playingResults.value = false
  }
}


async function addAllResultsToQueue() {
  if (
    !results.value.length ||
    playingResults.value ||
    addingResults.value
  ) {
    return
  }


  addingResults.value = true

  message.value = ''
  error.value = ''


  try {
    await queueAddMany(
      results.value
    )


    message.value =
      results.value.length === 1
        ? '1 resultado agregado a la cola.'
        : `${results.value.length} resultados agregados a la cola.`


  } catch (exception) {
    error.value =
      exception?.message ||
      'No fue posible agregar los resultados a la cola.'

  } finally {
    addingResults.value = false
  }
}


onBeforeUnmount(
  () => {
    clearTimeout(timer)

    /*
     * Evita que una respuesta tardía
     * modifique esta vista después
     * de haber salido de Buscar.
     */
    searchRun += 1
  }
)


function formatNumber(value) {
  return new Intl.NumberFormat(
    'es-MX'
  ).format(
    Number(value) || 0
  )
}
</script>


<template>
  <div>
    <p
      class="text-xs
             font-medium
             uppercase
             tracking-[0.18em]
             text-[#be5c2b]"
    >
      Buscar
    </p>


    <h1
      class="mt-1
             text-3xl
             font-semibold"
    >
      Encuentra tu música
    </h1>


    <!-- SEARCH -->
    <div
      class="relative
             mt-7"
    >
      <Search
        class="absolute
               left-5
               top-1/2
               h-5 w-5
               -translate-y-1/2
               text-black/30"
        :stroke-width="2"
      />


      <input
        v-model="query"
        autofocus
        type="search"
        placeholder="Canción, artista, álbum o #hashtag..."
        class="w-full
               rounded-[22px]
               border-0
               bg-[#f0eeeb]
               py-4
               pl-14
               pr-5
               text-sm
               outline-none
               placeholder:text-black/25
               focus:ring-2
               focus:ring-[#be5c2b]/20"
      />
    </div>


    <!-- RESULTS -->
    <div
      class="mt-7"
    >
      <!-- SEARCHING -->
      <p
        v-if="searching"
        class="text-sm
               text-black/35"
      >
        Buscando…
      </p>


      <!-- EMPTY -->
      <p
        v-else-if="
          query &&
          !results.length &&
          !hashtagResults.length
        "
        class="text-sm
               text-black/35"
      >
        No encontramos resultados.
      </p>


      <template v-else>
        <!-- HASHTAGS -->
        <section
          v-if="hashtagResults.length"
          class="mb-7"
        >
          <div
            class="mb-3
                   flex items-center
                   gap-2"
          >
            <Hash
              class="h-4 w-4
                     text-[#be5c2b]"
              :stroke-width="2.2"
            />

            <p
              class="text-xs
                     font-semibold
                     uppercase
                     tracking-[0.14em]
                     text-black/40"
            >
              Hashtags
            </p>
          </div>


          <div
            class="grid
                   gap-2
                   sm:grid-cols-2
                   xl:grid-cols-3"
          >
            <button
              v-for="item in hashtagResults"
              :key="item.name"
              type="button"
              class="group
                     flex items-center
                     justify-between
                     gap-4
                     rounded-[18px]
                     bg-[#f0eeeb]
                     px-4 py-3
                     text-left
                     transition
                     hover:-translate-y-0.5
                     hover:bg-[#e9e5e1]"
              @click="
                openHashtag(
                  item
                )
              "
            >
              <div
                class="min-w-0"
              >
                <p
                  class="truncate
                         text-sm
                         font-semibold"
                >
                  <span
                    class="text-[#be5c2b]"
                  >
                    #
                  </span>{{ item.name }}
                </p>

                <p
                  v-if="
                    hashtagLabel(item)
                  "
                  class="mt-1
                         text-[10px]
                         text-black/35"
                >
                  {{ hashtagLabel(item) }}
                </p>
              </div>


              <div
                class="shrink-0
                       text-right"
              >
                <p
                  class="text-sm
                         font-semibold
                         text-black/60"
                >
                  {{
                    formatNumber(
                      item.count
                    )
                  }}
                </p>

                <p
                  class="text-[9px]
                         text-black/30"
                >
                  {{
                    Number(item.count) === 1
                      ? 'canción'
                      : 'canciones'
                  }}
                </p>
              </div>
            </button>
          </div>
        </section>


        <!-- SONG RESULT HEADER -->
        <div
          v-if="results.length"
          class="mb-4
                 flex
                 flex-col
                 gap-3
                 sm:flex-row
                 sm:items-center
                 sm:justify-between"
        >
          <div>
            <p
              class="text-xs
                     font-semibold
                     uppercase
                     tracking-[0.14em]
                     text-black/40"
            >
              Canciones
            </p>

            <p
              class="mt-1
                     text-xs
                     text-black/35"
            >
              {{ formatNumber(total) }}
              resultados
            </p>
          </div>


          <!-- BULK PLAYBACK ACTIONS -->
          <div
            class="flex
                   flex-wrap
                   items-center
                   gap-2"
          >
            <button
              class="inline-flex
                     items-center
                     gap-2
                     rounded-full
                     bg-[#252525]
                     px-4 py-2
                     text-xs
                     font-semibold
                     text-white
                     transition
                     hover:bg-black
                     disabled:cursor-not-allowed
                     disabled:opacity-40"
              :disabled="
                playingResults ||
                addingResults
              "
              @click="
                playAllResults
              "
            >
              <Loader2
                v-if="
                  playingResults
                "
                class="h-4 w-4
                       animate-spin"
              />

              <Play
                v-else
                class="h-4 w-4"
                :stroke-width="2"
              />

              {{
                playingResults
                  ? 'Reproduciendo…'
                  : 'Reproducir resultados'
              }}
            </button>


            <button
              class="inline-flex
                     items-center
                     gap-2
                     rounded-full
                     bg-[#f0eeeb]
                     px-4 py-2
                     text-xs
                     font-semibold
                     text-black/60
                     transition
                     hover:bg-[#e7e3df]
                     disabled:cursor-not-allowed
                     disabled:opacity-40"
              :disabled="
                playingResults ||
                addingResults
              "
              @click="
                addAllResultsToQueue
              "
            >
              <Loader2
                v-if="
                  addingResults
                "
                class="h-4 w-4
                       animate-spin"
              />

              <ListPlus
                v-else
                class="h-4 w-4"
                :stroke-width="2"
              />

              {{
                addingResults
                  ? 'Agregando…'
                  : 'Agregar todos a cola'
              }}
            </button>
          </div>
        </div>


        <!-- SUCCESS MESSAGE -->
        <div
          v-if="message"
          class="mb-4
                 rounded-[16px]
                 bg-emerald-50
                 px-4 py-3
                 text-xs
                 font-medium
                 text-emerald-700"
        >
          {{ message }}
        </div>


        <!-- ERROR -->
        <div
          v-if="error"
          class="mb-4
                 rounded-[16px]
                 bg-red-50
                 px-4 py-3
                 text-xs
                 font-medium
                 text-red-600"
        >
          {{ error }}
        </div>


        <!-- SONGS -->
        <SongRow
          v-for="item in results"
          :key="item.file"
          :song="item"
        />
      </template>
    </div>
  </div>
</template>