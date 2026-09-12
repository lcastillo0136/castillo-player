<script setup>
import {
  computed,
  onMounted,
  ref,
  watch
} from 'vue'

import {
  ChevronDown,
  ChevronUp,
  Ellipsis,
  Heart,
  ListMusic,
  Music2,
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
  from '../components/WaveformProgress.vue'

import AudioOutputSelector
  from '../components/AudioOutputSelector.vue'

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
  isFavorite,

  togglePlayback,
  previous,
  next,
  seek,
  setVolume,
  setRandom,
  setRepeat,
  toggleFavorite
} = useCastilloApi()

const {
  goBack,
  navigate
} = useCastilloNavigation()

onMounted(() => {
  const main =
    document.getElementById(
      'castillo-main-scroll'
    )

  main?.scrollTo({
    top: 0,
    behavior: 'auto'
  })
})

const menuOpen = ref(false)

const localVolume = ref(0)
const previousVolume = ref(50)

const lyricsSwipeStartY = ref(null)
const lyricsSwipeStartX = ref(null)

const lyricsSwipeOffset = ref(0)
const lyricsSwipeHorizontal = ref(0)
const lyricsSwiping = ref(false)

const LYRICS_SWIPE_THRESHOLD = 55
const LYRICS_SWIPE_MAX = 90

/*
 * Forma visual de la onda.
 * No representa PCM real todavía;
 * sirve como barra de seek visual.
 */
const waveform = [
]

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
    Math.floor(
      Number(value) || 0
    )
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

function startLyricsSwipe(event) {
  if (!song.value?.file) {
    return
  }

  lyricsSwipeStartY.value =
    event.clientY

  lyricsSwipeStartX.value =
    event.clientX

  lyricsSwipeOffset.value = 0
  lyricsSwipeHorizontal.value = 0

  lyricsSwiping.value = true

  event.currentTarget
    .setPointerCapture?.(
      event.pointerId
    )
}


function moveLyricsSwipe(event) {
  if (
    !lyricsSwiping.value ||
    lyricsSwipeStartY.value === null ||
    lyricsSwipeStartX.value === null
  ) {
    return
  }

  const deltaY =
    lyricsSwipeStartY.value -
    event.clientY

  const deltaX =
    event.clientX -
    lyricsSwipeStartX.value

  lyricsSwipeHorizontal.value =
    Math.abs(deltaX)

  /*
   * Solo permitimos movimiento
   * hacia arriba.
   */
  lyricsSwipeOffset.value =
    Math.min(
      LYRICS_SWIPE_MAX,
      Math.max(
        0,
        deltaY
      )
    )
}


function finishLyricsSwipe() {
  if (!lyricsSwiping.value) {
    return
  }

  const shouldOpen =
    lyricsSwipeOffset.value >=
      LYRICS_SWIPE_THRESHOLD &&
    lyricsSwipeOffset.value >
      lyricsSwipeHorizontal.value


  lyricsSwiping.value = false


  if (shouldOpen) {
    lyricsSwipeOffset.value =
      LYRICS_SWIPE_MAX

    window.setTimeout(() => {
      navigate('lyrics')

      lyricsSwipeOffset.value = 0
    }, 90)

    return
  }


  lyricsSwipeOffset.value = 0
}


function cancelLyricsSwipe() {
  lyricsSwiping.value = false
  lyricsSwipeOffset.value = 0
  lyricsSwipeHorizontal.value = 0

  lyricsSwipeStartY.value = null
  lyricsSwipeStartX.value = null
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
</script>

<template>
  <div
    class="relative mx-auto
           flex min-h-full
           w-full max-w-[440px]
           flex-col
           px-6 pb-7
           lg:hidden"
    style="
      padding-top:
        calc(
          122px +
          env(safe-area-inset-top)
        );
    "
  >
    <!-- MOBILE FIXED HEADER -->
    <header
      class="fixed
             inset-x-0
             top-0
             z-[300]
             border-b
             border-black/[0.035]
             bg-[#faf9f7]/95
             backdrop-blur-xl
             lg:hidden"
      style="
        padding-top:
          env(safe-area-inset-top);
      "
    >
      <div
        class="mx-auto
               grid
               min-h-[64px]
               w-full
               max-w-[440px]
               grid-cols-[44px_1fr_44px]
               items-center
               px-6"
      >
        <!-- BACK -->
        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-black/[0.035]
                 text-[#111827]
                 transition
                 active:scale-95
                 active:bg-black/[0.08]"
          aria-label="Volver"
          @click="goBack"
        >
          <ChevronDown
            class="h-6 w-6"
            :stroke-width="2"
          />
        </button>


        <!-- TITLE -->
        <div
          class="min-w-0
                 text-center"
        >
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Castillo
          </p>

          <h1
            class="mt-0.5
                   text-[14px]
                   font-semibold
                   text-[#111827]"
          >
            Ahora suena
          </h1>
        </div>


        <!-- QUEUE -->
        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-black/[0.035]
                 text-[#111827]
                 transition
                 active:scale-95
                 active:bg-black/[0.08]"
          aria-label="Ver cola"
          @click="
            navigate('queue')
          "
        >
          <ListMusic
            class="h-[21px] w-[21px]"
            :stroke-width="2"
          />
        </button>
      </div>
    </header>

    <!-- COVER -->
    <section
      class="mt-3 flex
             justify-center"
    >
      <div
        class="aspect-square
               w-[78%]
               max-w-[310px]
               overflow-hidden
               rounded-[34px]
               bg-[#eef0f3]
               shadow-[0_22px_45px_rgba(100,80,80,.12)]"
      >
        <img
          v-if="song.file"
          :src="coverUrl(song.file)"
          class="h-full w-full
                 object-cover"
          alt="Carátula"
        />

        <div
          v-else
          class="flex h-full
                 items-center
                 justify-center"
        >
          <Music2
            class="h-16 w-16
                   text-black/10"
            :stroke-width="1.8"
          />
        </div>
      </div>
    </section>

    <!-- TRACK -->
    <section
      class="mt-7 grid
             grid-cols-[44px_minmax(0,1fr)_44px]
             items-center gap-2"
    >
      <button
        v-if="song.file"
        class="flex h-11 w-11
               items-center justify-center
               rounded-full
               text-[#9ca6b4]"
        @click="
          toggleFavorite(
            song.file
          )
        "
      >
        <Heart
          class="h-7 w-7"
          :class="
            isFavorite(song.file)
              ? 'text-[#ff6470]'
              : 'text-[#9ca6b4]'
          "
          :fill="
            isFavorite(song.file)
              ? 'currentColor'
              : 'none'
          "
          :stroke-width="2"
        />
      </button>

      <div
        v-else
        class="h-11 w-11"
      />


      <div
        class="min-w-0
               text-center"
      >
        <h2
          class="truncate
                 text-[22px]
                 font-semibold
                 tracking-[-0.02em]
                 text-[#111827]"
        >
          {{
            song.title ||
            'Sin reproducción'
          }}
        </h2>

        <div
          class="mt-2
                 flex
                 flex-col
                 items-center
                 gap-1"
        >
          <!-- ARTISTA -->
          <button
            v-if="song.artist"
            class="max-w-full
                   truncate
                   text-[15px]
                   font-medium
                   text-[#9ba4b2]
                   transition
                   hover:text-[#be5c2b]
                   active:text-[#be5c2b]"
            @click="openCurrentArtist"
          >
            {{ song.artist }}
          </button>

          <!-- ÁLBUM -->
          <button
            v-if="song.album"
            class="max-w-full
                   truncate
                   text-[11px]
                   font-normal
                   text-black/25
                   transition
                   hover:text-[#be5c2b]
                   active:text-[#be5c2b]"
            @click="openCurrentAlbum"
          >
            {{ song.album }}
          </button>
        </div>
      </div>

      <button
        class="flex h-11 w-11
               items-center justify-center
               rounded-full
               text-[#9ca6b4]"
        @click="
          menuOpen = !menuOpen
        "
      >
        <Ellipsis
          class="h-6 w-6"
          :stroke-width="2"
        />
      </button>
    </section>

    <!-- OPTIONS -->
    <section
      v-if="menuOpen"
      class="mt-4
             rounded-[22px]
             bg-[#f3f4f6]
             p-4"
    >
      <div
        class="flex items-center
               gap-3"
      >
        <button
          class="text-[#9ca6b4]"
          @click="toggleMute"
        >
          <VolumeX
            v-if="volume === 0"
            class="h-5 w-5"
            :stroke-width="2"
          />

          <Volume2
            v-else
            class="h-5 w-5"
            :stroke-width="2"
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
                 text-[10px]
                 tabular-nums
                 text-[#9ca6b4]"
        >
          {{ Math.round(volume) }}
        </span>
      </div>
    </section>

    <!-- REAL WAVEFORM -->
    <section class="mt-8">
      <WaveformProgress
        class="min-w-0 flex-1"
        :file="song.file || ''"
        :current="elapsed"
        :duration="currentDuration"
        :bars="220"
        :visual-bars="100"
        :height="50"
        active-color="#ff6470"
        inactive-color="#dce0e5"
        @seek="seek"
      />

      <div
        class="mt-1 flex
               justify-between
               text-[11px]
               font-medium
               tabular-nums"
      >
        <span class="text-[#ff6470]">
          {{ formatTime(elapsed) }}
        </span>

        <span class="text-[#c1c6cd]">
          {{
            formatTime(
              currentDuration
            )
          }}
        </span>
      </div>
    </section>

    <!-- CONTROLS -->
    <section
      class="mt-6 flex
             items-center
             justify-between
             px-1"
    >
      <button
        class="flex h-11 w-11
               items-center justify-center"
        :class="
          random
            ? 'text-[#ff6470]'
            : 'text-[#9ca6b4]'
        "
        @click="
          setRandom(!random)
        "
      >
        <Shuffle
          class="h-[22px] w-[22px]"
          :stroke-width="2"
        />
      </button>


      <button
        class="flex h-12 w-12
               items-center justify-center
               text-[#111827]"
        @click="previous"
      >
        <SkipBack
          class="h-7 w-7"
          :stroke-width="2.2"
        />
      </button>


      <button
        class="flex h-[72px]
               w-[72px]
               items-center
               justify-center
               rounded-full
               bg-[#ff6470]
               text-white
               shadow-[0_16px_30px_rgba(255,100,112,.4)]
               transition
               active:scale-95"
        @click="togglePlayback"
      >
        <Pause
          v-if="playing"
          class="h-9 w-9"
          :stroke-width="2.4"
        />

        <Play
          v-else
          class="ml-1
                 h-9 w-9"
          :stroke-width="2.4"
        />
      </button>

      <button
        class="flex h-12 w-12
               items-center justify-center
               text-[#111827]"
        @click="next"
      >
        <SkipForward
          class="h-7 w-7"
          :stroke-width="2.2"
        />
      </button>

      <button
        class="flex h-11 w-11
               items-center justify-center"
        :class="
          repeat
            ? 'text-[#ff6470]'
            : 'text-[#9ca6b4]'
        "
        @click="
          setRepeat(!repeat)
        "
      >
        <Repeat2
          class="h-[22px] w-[22px]"
          :stroke-width="2"
        />
      </button>
    </section>
    <!-- AUDIO OUTPUT -->
    <div
      class="mt-6
             flex
             justify-center"
    >
      <AudioOutputSelector />
    </div>

    <!-- SWIPE TO LYRICS -->
    <div
      class="mt-7 flex
             justify-center"
    >
      <div
        class="flex
               min-h-[76px]
               w-full
               max-w-[280px]
               touch-none
               select-none
               flex-col
               items-center
               justify-center
               rounded-[24px]
               text-center
               transition-colors"
        :class="
          lyricsSwiping
            ? 'bg-black/[0.025]'
            : ''
        "
        role="button"
        tabindex="0"
        aria-label="
          Desliza hacia arriba para ver letras
        "
        @pointerdown="startLyricsSwipe"
        @pointermove="moveLyricsSwipe"
        @pointerup="finishLyricsSwipe"
        @pointercancel="cancelLyricsSwipe"
        @keydown.enter="
          navigate('lyrics')
        "
      >
        <div
          class="transition-transform
                 duration-75"
          :style="{
            transform:
              `translateY(-${
                lyricsSwipeOffset * 0.35
              }px)`
          }"
        >
          <ChevronUp
            class="h-4 w-4
                   text-black/30"
            :class="
              lyricsSwipeOffset >=
              LYRICS_SWIPE_THRESHOLD
                ? 'text-[#ff6470]'
                : ''
            "
          />
        </div>

        <p
          class="mt-2
                 text-[10px]
                 font-medium
                 transition-colors"
          :class="
            lyricsSwipeOffset >=
            LYRICS_SWIPE_THRESHOLD
              ? 'text-[#ff6470]'
              : 'text-black/30'
          "
        >
          {{
            lyricsSwipeOffset >=
            LYRICS_SWIPE_THRESHOLD
              ? 'Suelta para ver letras'
              : 'Desliza hacia arriba para letras'
          }}
        </p>


        <!-- GESTURE PROGRESS -->
        <div
          class="mt-3
                 h-[3px]
                 w-14
                 overflow-hidden
                 rounded-full
                 bg-black/[0.06]"
        >
          <div
            class="h-full
                   rounded-full
                   bg-[#ff6470]
                   transition-[width]
                   duration-75"
            :style="{
              width:
                `${
                  Math.min(
                    100,
                    lyricsSwipeOffset /
                    LYRICS_SWIPE_THRESHOLD *
                    100
                  )
                }%`
            }"
          />
        </div>
      </div>
    </div>
  </div>
</template>