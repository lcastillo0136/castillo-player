<script setup>
import {
  computed,
  ref,
  watch
} from 'vue'

import {
  Pause,
  Play,
  Repeat2,
  Shuffle,
  SkipBack,
  SkipForward,
  Volume2,
  VolumeX
} from 'lucide-vue-next'

import WaveformProgress
  from './WaveformProgress.vue'

import AudioOutputSelector
  from './AudioOutputSelector.vue'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

const {
  song,
  elapsed,
  currentDuration,
  playing,
  volume,
  random,
  repeat,

  coverUrl,

  togglePlayback,
  previous,
  next,
  seek,
  setVolume,
  setRandom,
  setRepeat
} = useCastilloApi()

const {
  navigate
} = useCastilloNavigation()

const localVolume = ref(0)
const previousVolume = ref(50)

watch(
  volume,
  value => {
    localVolume.value = value

    if (value > 0) {
      previousVolume.value = value
    }
  },
  {
    immediate: true
  }
)

function formatTime(value) {
  const total = Math.max(
    0,
    Math.floor(Number(value) || 0)
  )

  const minutes =
    Math.floor(total / 60)

  const seconds =
    total % 60

  return (
    `${minutes}:` +
    String(seconds).padStart(2, '0')
  )
}

function openCurrentArtist() {
  const artist =
    song.value?.artist

  if (!artist) {
    return
  }

  navigate(
    'artists',
    {
      artist
    }
  )
}


function openCurrentAlbum() {
  const album =
    song.value?.album

  if (!album) {
    return
  }

  navigate(
    'albums',
    {
      album,

      albumArtist:
        song.value?.albumartist ||
        song.value?.artist ||
        '—'
    }
  )
}

async function changeVolume() {
  await setVolume(
    localVolume.value
  )
}

async function toggleMute() {
  if (volume.value > 0) {
    previousVolume.value =
      volume.value

    await setVolume(0)
  } else {
    await setVolume(
      previousVolume.value || 50
    )
  }
}
</script>

<template>
  <footer
    class="fixed
         bottom-0
         left-[var(--castillo-sidebar-width)]
         right-0
         z-[100]
         hidden
         border-t border-black/5
         bg-white/95
         shadow-[0_-6px_30px_rgba(0,0,0,0.04)]
         backdrop-blur-xl
         transition-[left]
         duration-300
         ease-out
         lg:block"
  >
    
    <!-- DESKTOP -->
    <div
      class="flex h-[112px]
         items-center gap-8
         px-7"
    >
      <div
        class="flex w-[260px]
               min-w-0 items-center gap-3"
      >
        <img
          v-if="song.file"
          :src="coverUrl(song.file)"
          class="h-14 w-14
                 shrink-0 rounded-xl
                 object-cover"
        />

        <div class="min-w-0 flex-1">
          <!-- TITLE -->
          <p
            class="truncate
                   text-sm
                   font-semibold"
            :title="
              song.title ||
              'Sin reproducción'
            "
          >
            {{
              song.title ||
              'Sin reproducción'
            }}
          </p>


          <!-- ARTIST -->
          <button
            v-if="song.artist"
            class="mt-0.5
                   block
                   max-w-full
                   truncate
                   text-left
                   text-xs
                   text-black/40
                   transition
                   hover:text-[#be5c2b]"
            title="Ver artista"
            @click="openCurrentArtist"
          >
            {{ song.artist }}
          </button>

          <span
            v-else
            class="mt-0.5
                   block
                   text-xs
                   text-black/30"
          >
            —
          </span>


          <!-- ALBUM -->
          <button
            v-if="song.album"
            class="mt-0.5
                   block
                   max-w-full
                   truncate
                   text-left
                   text-[10px]
                   text-black/25
                   transition
                   hover:text-[#be5c2b]"
            title="Ver álbum"
            @click="openCurrentAlbum"
          >
            {{ song.album }}
          </button>
        </div>
      </div>

      <div
        class="flex min-w-0
               flex-1 flex-col"
      >
        <div
          class="flex items-center
                 justify-center gap-4"
        >
          <button
            class="rounded-full p-2"
            :class="
              random
                ? 'text-[#be5c2b]'
                : 'text-black/35 hover:text-black'
            "
            @click="
              setRandom(!random)
            "
          >
            <Shuffle
              class="h-5 w-5"
            />
          </button>

          <button
            class="rounded-full p-2
                   text-black/55
                   hover:text-black"
            @click="previous"
          >
            <SkipBack
              class="h-6 w-6"
            />
          </button>

          <button
            class="flex h-14 w-14
                   items-center
                   justify-center
                   rounded-full
                   bg-[#ff6470]
                   text-white
                   shadow-[0_10px_30px_rgba(255,100,112,.35)]
                   transition
                   hover:scale-105"
            @click="togglePlayback"
          >
            <Pause
              v-if="playing"
              class="h-7 w-7"
            />

            <Play
              v-else
              class="ml-1 h-7 w-7"
            />
          </button>

          <button
            class="rounded-full p-2
                   text-black/55
                   hover:text-black"
            @click="next"
          >
            <SkipForward
              class="h-6 w-6"
            />
          </button>

          <button
            class="rounded-full p-2"
            :class="
              repeat
                ? 'text-[#be5c2b]'
                : 'text-black/35 hover:text-black'
            "
            @click="
              setRepeat(!repeat)
            "
          >
            <Repeat2
              class="h-5 w-5"
            />
          </button>
        </div>

        <div
          class="mt-2 flex
                 items-center gap-3"
        >
          <span
            class="w-10 text-right
                   text-[10px]
                   tabular-nums
                   text-black/35"
          >
            {{ formatTime(elapsed) }}
          </span>

          <WaveformProgress
            class="min-w-0 flex-1"
            :file="song.file || ''"
            :current="elapsed"
            :duration="currentDuration"
            :bars="220"
            :visual-bars="150"
            :height="34"
            active-color="#ff6470"
            inactive-color="#dce0e5"
            @seek="seek"
          />

          <span
            class="w-10
                   text-[10px]
                   tabular-nums
                   text-black/35"
          >
            {{
              formatTime(
                currentDuration
              )
            }}
          </span>
        </div>
      </div>

      <div
        class="flex w-[360px]
               items-center
               justify-end
               gap-3"
      >
        <AudioOutputSelector />
        
        <button
          class="text-black/45"
          @click="toggleMute"
        >
          <VolumeX
            v-if="volume === 0"
            class="h-5 w-5"
          />

          <Volume2
            v-else
            class="h-5 w-5"
          />
        </button>

        <input
          v-model.number="localVolume"
          type="range"
          min="0"
          max="100"
          class="castillo-range
                 min-w-0 flex-1"
          @change="changeVolume"
        />

        <span
          class="w-7 text-right
                 text-xs
                 tabular-nums
                 text-black/35"
        >
          {{ Math.round(volume) }}
        </span>
      </div>
    </div>
  </footer>
</template>