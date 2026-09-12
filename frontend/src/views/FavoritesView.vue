<script setup>
import {
  computed
} from 'vue'

import {
  Play
} from 'lucide-vue-next'

import SongRow
  from '../components/SongRow.vue'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

const {
  library,
  favorites,
  playFiles
} = useCastilloApi()

const favoriteSongs = computed(() => {
  const wanted =
    new Set(favorites.value)

  return library.value.songs.filter(
    song => wanted.has(song.file)
  )
})
</script>

<template>
  <div>
    <div
      class="flex items-end
             justify-between gap-4"
    >
      <div>
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
          Favoritos
        </h1>
      </div>

      <button
        v-if="favoriteSongs.length"
        class="flex items-center gap-2
               rounded-full
               bg-[#252525]
               px-5 py-2.5
               text-xs text-white"
        @click="playFiles(favoriteSongs)"
      >
        <Play
          class="h-4 w-4"
          :stroke-width="2"
        />
        Reproducir todo
      </button>
    </div>

    <div class="mt-7">
      <p
        v-if="!favoriteSongs.length"
        class="text-sm text-black/35"
      >
        Todavía no tienes canciones favoritas.
      </p>

      <SongRow
        v-for="item in favoriteSongs"
        :key="item.file"
        :song="item"
      />
    </div>
  </div>
</template>