<script setup>
import {
  computed,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  Play,
  Plus,
  Trash2
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
  playlists,
  library,

  createPlaylist,
  deletePlaylist,
  playPlaylist
} = useCastilloApi()

const {
  route,
  navigate,
  goBack
} = useCastilloNavigation()

const newName = ref('')
const selected = computed(
  () => route.value.playlist || ''
)

const names = computed(() =>
  Object.keys(playlists.value || {})
)

const selectedFiles = computed(() =>
  playlists.value?.[selected.value] || []
)

const songMap = computed(() => {
  return new Map(
    library.value.songs.map(
      song => [
        song.file,
        song
      ]
    )
  )
})

const selectedSongs = computed(() => {
  return selectedFiles.value.map(
    file =>
      songMap.value.get(file) || {
        file,
        title:
          file.split('/').pop()
      }
  )
})

async function create() {
  const name = newName.value.trim()

  if (!name) {
    return
  }

  await createPlaylist(name)

  navigate(
    'playlists',
    {
      playlist: name
    }
  )
  newName.value = ''
}

async function removePlaylist() {
  if (!selected.value) {
    return
  }

  if (
    !window.confirm(
      `¿Eliminar la playlist "${selected.value}"?`
    )
  ) {
    return
  }

  const name = selected.value

  navigate('playlists')

  await deletePlaylist(name)
}

watch(
  names,
  value => {
    if (
      selected.value &&
      !value.includes(selected.value)
    ) {
      selected.value = ''
    }
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
    <!-- LISTADO -->
    <template v-if="!selected">
      <p
        class="text-xs font-medium
               uppercase tracking-[0.18em]
               text-[#be5c2b]"
      >
        Mi música
      </p>

      <h1
        class="mt-1 text-3xl font-semibold"
      >
        Playlists
      </h1>

      <form
        class="mt-7 flex max-w-xl gap-2"
        @submit.prevent="create"
      >
        <input
          v-model="newName"
          type="text"
          placeholder="Nombre de la nueva playlist"
          class="min-w-0 flex-1
                 rounded-2xl
                 bg-[#f0eeeb]
                 px-5 py-3
                 text-sm outline-none"
        />

        <button
          class="flex items-center gap-2
                 rounded-2xl
                 bg-[#252525]
                 px-5 py-3
                 text-xs text-white"
        >
          <Plus class="h-4 w-4" />
          Crear
        </button>
      </form>

      <div
        class="mt-8 grid
               grid-cols-1 gap-4
               sm:grid-cols-2
               xl:grid-cols-3"
      >
        <button
          v-for="name in names"
          :key="name"
          class="rounded-[24px]
                 bg-[#f1eeeb]
                 p-6 text-left
                 transition
                 hover:-translate-y-1"
          @click="
            navigate(
              'playlists',
              {
                playlist: name
              }
            )
          "
        >
          <div
            class="flex h-14 w-14
                   items-center justify-center
                   rounded-2xl
                   bg-[#be5c2b]
                   text-xl text-white"
          >
            ♪
          </div>

          <p
            class="mt-5
                   text-lg font-semibold"
          >
            {{ name }}
          </p>

          <p
            class="mt-1
                   text-xs text-black/35"
          >
            {{
              playlists[name]?.length || 0
            }}
            canciones
          </p>
        </button>

        <p
          v-if="!names.length"
          class="text-sm text-black/35"
        >
          Todavía no has creado playlists.
        </p>
      </div>
    </template>

    <!-- PLAYLIST -->
    <template v-else>
      <button
        class="flex items-center
               gap-2 text-sm text-black/45"
        @click="goBack"
      >
        <ArrowLeft class="h-4 w-4" />
        Todas las playlists
      </button>

      <div
        class="mt-6 flex flex-wrap
               items-end justify-between
               gap-4"
      >
        <div>
          <p
            class="text-xs font-medium
                   uppercase tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Playlist
          </p>

          <h1
            class="mt-1 text-3xl font-semibold"
          >
            {{ selected }}
          </h1>

          <p
            class="mt-2 text-sm
                   text-black/35"
          >
            {{ formatNumber(selectedSongs.length) }}
            canciones
          </p>
        </div>

        <div class="flex gap-2">
          <button
            v-if="selectedSongs.length"
            class="flex items-center gap-2
                   rounded-full
                   bg-[#252525]
                   px-5 py-2.5
                   text-xs text-white"
            @click="playPlaylist(selected)"
          >
            <Play class="h-4 w-4" />
            Reproducir
          </button>

          <button
            class="flex items-center gap-2
                   rounded-full
                   bg-red-50
                   px-5 py-2.5
                   text-xs text-red-600"
            @click="removePlaylist"
          >
            <Trash2 class="h-4 w-4" />
            Eliminar
          </button>
        </div>
      </div>

      <div class="mt-7">
        <SongRow
          v-for="item in selectedSongs"
          :key="item.file"
          :song="item"
          :removable-from="selected"
        />

        <p
          v-if="!selectedSongs.length"
          class="text-sm text-black/35"
        >
          Esta playlist está vacía.
        </p>
      </div>
    </template>
  </div>
</template>