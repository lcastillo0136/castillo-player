<script setup>
import {
  computed,
  nextTick,
  ref
} from 'vue'

import {
  ArrowLeft,
  ListMusic,
  Play
} from 'lucide-vue-next'

import SongRow
  from '../components/SongRow.vue'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

const props = defineProps({
  mode: {
    type: String,
    default: 'songs'
  }
})

const {
  route,
  navigate,
  goBack
} = useCastilloNavigation()

const {
  library,
  coverUrl,
  playFiles,
  playAll,
  queueAddMany
} = useCastilloApi()

const selectedAlbum = computed(() => {
  if (!route.value.album) {
    return null
  }

  return {
    album: route.value.album,
    artist:
      route.value.albumArtist === '—'
        ? ''
        : route.value.albumArtist
  }
})

const selectedArtist = computed(
  () => route.value.artist || null
)

const title = computed(() => {
  if (props.mode === 'albums') {
    return 'Álbumes'
  }

  if (props.mode === 'artists') {
    return 'Artistas'
  }

  return 'Canciones'
})

const selectedSongs = computed(() => {
  if (selectedAlbum.value) {
    return library.value.songs.filter(
      song =>
        song.album ===
          selectedAlbum.value.album &&
        (
          !selectedAlbum.value.artist ||
          song.artist ===
            selectedAlbum.value.artist
        )
    )
  }

  if (selectedArtist.value) {
    return library.value.songs.filter(
      song =>
        song.artist ===
        selectedArtist.value
    )
  }

  return []
})

async function scrollToTop() {
  await nextTick()

  const container =
    document.getElementById(
      'castillo-main-scroll'
    )

  if (!container) {
    return
  }

  container.scrollTo({
    top: 0,
    left: 0,
    behavior: 'auto'
  })
}


function openAlbum(album) {
  navigate(
    'albums',
    {
      album: album.album,
      albumArtist:
        album.artist || '—'
    }
  )
}


function openArtist(artist) {
  navigate(
    'artists',
    {
      artist
    }
  )
}

async function back() {
  selectedAlbum.value = null
  selectedArtist.value = null

  await scrollToTop()
}

function formatNumber(value) {
  return new Intl.NumberFormat(
    'es-MX'
  ).format(
    Number(value) || 0
  )
}

function artistText(value) {
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
</script>

<template>
  <div>
    <div
      class="mb-8 flex items-end
             justify-between gap-4"
    >
      <div>
        <p
          class="text-xs font-medium
                 uppercase tracking-[0.18em]
                 text-[#be5c2b]"
        >
          Biblioteca
        </p>

        <h1
          class="mt-1 text-3xl
                 font-semibold tracking-tight"
        >
          {{ title }}
        </h1>
      </div>
      <div class="flex items-center gap-3">
        <span
          class="text-xs text-black/35"
        >
          {{ formatNumber(library.total) }} canciones
        </span>
        <button
          v-if="mode === 'songs' && library.total"
          class="flex items-center
                 gap-2 rounded-full
                 bg-[#252525]
                 px-5 py-2.5
                 text-xs font-medium
                 text-white
                 transition
                 hover:bg-black"
          @click="playAll"
        >
          <Play
            class="h-4 w-4"
            :stroke-width="2"
          />

          Reproducir todo
        </button>
      </div>
    </div>

    <!-- CANCIONES -->
    <div
      v-if="mode === 'songs'"
      class="space-y-1"
    >
      <SongRow
        v-for="item in library.songs"
        :key="item.file"
        :song="item"
      />
    </div>

    <!-- ÁLBUMES -->
    <template
      v-else-if="mode === 'albums'"
    >
      <div
        v-if="!selectedAlbum"
        class="grid grid-cols-2
               gap-5
               sm:grid-cols-3
               2xl:grid-cols-5"
      >
        <button
          v-for="album in library.albums"
          :key="
            `${album.artist}-${album.album}`
          "
          class="text-left"
          @click="openAlbum(album)"
        >
          <div
            class="aspect-square
                   overflow-hidden
                   rounded-[24px]
                   bg-[#ece8e4]"
          >
            <img
              :src="coverUrl(album.file)"
              class="h-full w-full object-cover
                     transition duration-300
                     hover:scale-105"
            />
          </div>

          <p
            class="mt-3 truncate
                   text-sm font-semibold"
          >
            {{ album.album }}
          </p>

          <p
            class="mt-1 truncate
                   text-xs text-black/40"
          >
            {{ album.artist || '—' }}
          </p>
        </button>
      </div>

      <div v-else>
        <button
          class="mb-5 flex items-center
                 gap-2 text-sm text-black/45"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Todos los álbumes
        </button>

        <div
          class="mb-6 flex flex-wrap
                 items-end justify-between
                 gap-4"
        >
          <div>
            <h2
              class="text-2xl font-semibold"
            >
              {{ selectedAlbum.album }}
            </h2>

            <p
              class="mt-1 text-sm
                     text-black/40"
            >
              {{ selectedAlbum.artist }}
            </p>
          </div>

          <div class="flex gap-2">
            <button
              class="flex items-center gap-2
                     rounded-full
                     bg-[#252525]
                     px-5 py-2.5
                     text-xs text-white"
              @click="
                playFiles(selectedSongs)
              "
            >
              <Play class="h-4 w-4" />
              Reproducir
            </button>

            <button
              class="flex items-center gap-2
                     rounded-full
                     bg-black/5
                     px-5 py-2.5
                     text-xs"
              @click="
                queueAddMany(selectedSongs)
              "
            >
              <ListMusic
                class="h-4 w-4"
              />
              A cola
            </button>
          </div>
        </div>

        <SongRow
          v-for="item in selectedSongs"
          :key="item.file"
          :song="item"
        />
      </div>
    </template>

    <!-- ARTISTAS -->
    <template v-else>
      <div
        v-if="!selectedArtist"
        class="grid grid-cols-2
               gap-4
               sm:grid-cols-3
               xl:grid-cols-4
               2xl:grid-cols-5"
      >
        <button
          v-for="artist in library.artists"
          :key="artist.artist"
          class="rounded-[24px]
                 bg-[#f1eeeb]
                 p-5 text-left
                 transition
                 hover:-translate-y-1"
          @click="
            openArtist(artist.artist)
          "
        >
          <div
            class="flex h-16 w-16
                   items-center justify-center
                   rounded-full
                   bg-[#625d5a]
                   text-xl font-semibold
                   text-[#f2f478]"
          >
            {{
              artistText(artist.artist)
                .slice(0, 1)
                .toUpperCase()
            }}
          </div>

          <p
            class="mt-4 truncate
                   text-sm font-semibold"
          >
            {{ artist.artist }}
          </p>

          <p
            class="mt-1 text-xs
                   text-black/35"
          >
            {{ formatNumber(artist.count) }} canciones
          </p>
        </button>
      </div>

      <div v-else>
        <button
          class="mb-5 flex items-center
                 gap-2 text-sm text-black/45"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Todos los artistas
        </button>

        <div
          class="mb-6 flex flex-wrap
                 items-end justify-between
                 gap-4"
        >
          <h2
            class="text-3xl font-semibold"
          >
            {{ selectedArtist }}
          </h2>

          <div class="flex gap-2">
            <button
              class="rounded-full
                     bg-[#252525]
                     px-5 py-2.5
                     text-xs text-white"
              @click="
                playFiles(selectedSongs)
              "
            >
              Reproducir artista
            </button>

            <button
              class="rounded-full
                     bg-black/5
                     px-5 py-2.5
                     text-xs"
              @click="
                queueAddMany(selectedSongs)
              "
            >
              Agregar a cola
            </button>
          </div>
        </div>

        <SongRow
          v-for="item in selectedSongs"
          :key="item.file"
          :song="item"
        />
      </div>
    </template>
  </div>
</template>