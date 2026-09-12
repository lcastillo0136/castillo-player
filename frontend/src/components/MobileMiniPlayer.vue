<script setup>
import {
  computed
} from 'vue'

import {
  Heart,
  Pause,
  Play,
  Music2
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'


const {
  song,
  playing,
  elapsed,
  currentDuration,

  coverUrl,
  isFavorite,
  toggleFavorite,

  togglePlayback
} = useCastilloApi()


const {
  navigate
} = useCastilloNavigation()


const progress = computed(() => {
  if (!currentDuration.value) {
    return 0
  }

  return Math.min(
    100,
    Math.max(
      0,
      elapsed.value /
      currentDuration.value *
      100
    )
  )
})


function openPlayer() {
  if (!song.value.file) {
    return
  }

  navigate('player')
}
</script>

<template>
  <div
    v-if="song.file"
    class="fixed
           bottom-[78px]
           left-3
           right-3
           z-40
           lg:hidden"
  >
    <div
      class="relative
             overflow-hidden
             rounded-[28px]
             bg-gradient-to-r
             from-[#252525]
             via-[#1d2432]
             to-[#111827]
             shadow-[0_18px_40px_rgba(0,0,0,.28)]"
    >
      <!-- glow suave -->
      <div
        class="pointer-events-none
               absolute
               right-4
               top-1/2
               h-16 w-16
               -translate-y-1/2
               rounded-full
               bg-[#ff6470]/15
               blur-2xl"
      />

      <button
        class="flex
               w-full
               items-center
               gap-3
               px-3
               py-3
               text-left"
        @click="openPlayer"
      >
        <!-- cover -->
        <div
          class="h-14 w-14
                 shrink-0
                 overflow-hidden
                 rounded-[18px]
                 bg-white/10"
        >
          <img
            v-if="song.file"
            :src="coverUrl(song.file)"
            alt="Carátula"
            class="h-full w-full object-cover"
          />

          <div
            v-else
            class="flex h-full w-full
                   items-center justify-center"
          >
            <Music2
              class="h-6 w-6 text-white/30"
              :stroke-width="2"
            />
          </div>
        </div>

        <!-- text -->
        <div class="min-w-0 flex-1">
          <p
            class="truncate
                   text-[15px]
                   font-semibold
                   text-white"
          >
            {{ song.title || 'Sin reproducción' }}
          </p>

          <p
            class="mt-1 truncate
                   text-[13px]
                   text-white/60"
          >
            {{ song.artist || '—' }}
          </p>
        </div>

        <!-- favorite -->
        <button
          class="relative z-10
                 flex h-11 w-11
                 shrink-0
                 items-center justify-center
                 rounded-full
                 text-white/85
                 active:bg-white/10"
          @click.stop="
            toggleFavorite(song.file)
          "
        >
          <Heart
            class="h-6 w-6"
            :class="
              isFavorite(song.file)
                ? 'text-[#ff8a5b]'
                : 'text-white/80'
            "
            :fill="
              isFavorite(song.file)
                ? 'currentColor'
                : 'none'
            "
            :stroke-width="2"
          />
        </button>

        <!-- play / pause -->
        <button
          class="relative z-10
                 flex h-[58px] w-[58px]
                 shrink-0
                 items-center justify-center
                 rounded-[20px]
                 bg-white
                 text-[#252525]
                 shadow-[0_8px_22px_rgba(255,255,255,.12)]
                 active:scale-95"
          @click.stop="togglePlayback"
        >
          <Pause
            v-if="playing"
            class="h-7 w-7"
            :stroke-width="2.6"
          />

          <Play
            v-else
            class="ml-0.5 h-7 w-7"
            :stroke-width="2.6"
          />
        </button>
      </button>

      <!-- progress -->
      <div
        class="absolute bottom-0 left-0 right-0
               h-[3px]
               bg-white/10"
      >
        <div
          class="h-full rounded-r-full
                 bg-[#ff6470]"
          :style="{
            width: `${progress}%`
          }"
        />
      </div>
    </div>
  </div>
</template>