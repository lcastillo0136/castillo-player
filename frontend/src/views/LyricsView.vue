<script setup>
import {
  nextTick,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  ListMusic,
  Music2,
  Pencil
} from 'lucide-vue-next'

import WaveformProgress
  from '../components/WaveformProgress.vue'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

import {
  useLyrics
} from '../composables/useLyrics'

const {
  song,
  elapsed,
  currentDuration,
  seek
} = useCastilloApi()

const {
  lyrics,
  credits,
  metadata,

  loadingLyrics,
  lyricsError,
  activeIndex
} = useLyrics()

const {
  currentView,
  goBack,
  navigate
} = useCastilloNavigation()

const lineElements = ref([])

function formatTime(value) {
  const total =
    Math.max(
      0,
      Math.floor(
        Number(value) || 0
      )
    )

  return (
    `${Math.floor(total / 60)}:` +
    String(
      total % 60
    ).padStart(2, '0')
  )
}

function lyricLineClass(index) {
  if (activeIndex.value < 0) {
    return 'text-xl sm:text-2xl opacity-40 scale-[0.88]'
  }

  const distance =
    Math.abs(
      index - activeIndex.value
    )

  if (distance === 0) {
    return `
      text-3xl
      sm:text-5xl
      opacity-100
      scale-100
      text-[#252525]
    `
  }

  if (distance === 1) {
    return `
      text-2xl
      sm:text-3xl
      opacity-65
      scale-[0.94]
      text-black/55
    `
  }

  if (distance === 2) {
    return `
      text-xl
      sm:text-2xl
      opacity-40
      scale-[0.88]
      text-black/45
    `
  }

  return `
    text-lg
    sm:text-xl
    opacity-20
    scale-[0.82]
    text-black/35
  `
}

async function seekToLyric(
  line,
  index
) {
  if (
    !line ||
    !Number.isFinite(
      Number(line.time)
    )
  ) {
    return
  }

  /*
   * Mover MPD al timestamp exacto
   * de esta línea.
   */
  await seek(
    Number(line.time)
  )

  /*
   * Centramos inmediatamente la línea
   * que el usuario seleccionó.
   */
  await nextTick()

  requestAnimationFrame(() => {
    const element =
      lineElements.value[index]

    element?.scrollIntoView({
      behavior: 'smooth',
      block: 'center',
      inline: 'nearest'
    })
  })
}

async function scrollToActiveLine(
  behavior = 'smooth'
) {
  const index =
    activeIndex.value

  if (index < 0) {
    return
  }

  /*
   * Esperamos a que Vue haya creado
   * las líneas y registrado sus refs.
   */
  await nextTick()

  /*
   * Un frame adicional ayuda cuando
   * acabamos de entrar a LyricsView
   * y el DOM todavía está calculando
   * alturas/scroll.
   */
  requestAnimationFrame(() => {
    const element =
      lineElements.value[index]

    if (!element) {
      return
    }

    element.scrollIntoView({
      behavior,
      block: 'center',
      inline: 'nearest'
    })
  })
}


/*
 * Reacciona tanto a:
 *
 * - cambio de línea por reproducción
 * - entrada a LyricsView con canción pausada
 * - carga de un nuevo archivo LRC
 */
watch(
  [
    activeIndex,
    () => lyrics.value.length
  ],

  async (
    [index, length],
    [oldIndex]
  ) => {
    if (
      index < 0 ||
      length === 0
    ) {
      return
    }

    /*
     * Al entrar a la pantalla usamos
     * scroll inmediato para no ver
     * cómo recorre toda la letra.
     *
     * Durante reproducción sí usamos
     * movimiento suave.
     */
    const behavior =
      oldIndex === undefined
        ? 'auto'
        : 'smooth'

    await scrollToActiveLine(
      behavior
    )
  },

  {
    immediate: true,
    flush: 'post'
  }
)

watch(
  () => song.value.file,
  () => {
    lineElements.value = []
  }
)

watch(
  currentView,

  async view => {
    if (
      view !== 'player' &&
      view !== 'lyrics'
    ) {
      return
    }

    await nextTick()

    const main =
      document.getElementById(
        'castillo-main-scroll'
      )

    if (!main) {
      return
    }

    main.scrollTo({
      top: 0,
      left: 0,
      behavior: 'auto'
    })
  },

  {
    immediate: true,
    flush: 'post'
  }
)
</script>


<template>
  <div
    class="mx-auto
         flex min-h-full
         max-w-5xl
         flex-col
         pt-[76px]
         lg:pt-0
         lg:-mt-[32px]"
  >

    <!-- MOBILE FIXED HEADER -->
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
        class="mx-auto
               grid
               min-h-[64px]
               w-full
               grid-cols-[44px_minmax(0,1fr)_44px]
               items-center
               px-5"
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
          <ArrowLeft
            class="h-5 w-5"
            :stroke-width="2"
          />
        </button>


        <!-- SONG -->
        <div
          class="min-w-0
                 px-3
                 text-center"
        >
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Letras
          </p>

          <p
            class="mt-0.5
                   truncate
                   text-[13px]
                   font-semibold
                   text-[#111827]"
          >
            {{
              song.title ||
              'Sin reproducción'
            }}
          </p>

          <p
            class="mt-0.5
                   truncate
                   text-[9px]
                   text-black/35"
          >
            {{ song.artist || '—' }}
          </p>
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
            class="h-5 w-5"
            :stroke-width="2"
          />
        </button>
      </div>
    </header>


    <!-- DESKTOP HEADER -->
    <header
      class="sticky
             top-0
             z-20
             hidden
             grid-cols-[1fr_auto_1fr]
             items-center
             border-b
             border-black/5
             bg-[#faf9f7]/95
             px-1
             py-3
             backdrop-blur-xl
             lg:grid"
    >
      <!-- LEFT -->
      <div
        class="flex
               justify-start"
      >
        <button
          class="group
                 inline-flex
                 h-10
                 items-center
                 gap-2
                 rounded-[14px]
                 px-3
                 text-sm
                 font-medium
                 text-black/45
                 transition
                 hover:bg-[#f0eeeb]
                 hover:text-black/70
                 active:scale-[0.97]"
          @click="goBack"
        >
          <ArrowLeft
            class="h-[18px] w-[18px]
                   transition-transform
                   group-hover:-translate-x-0.5"
            :stroke-width="1.8"
          />

          <span>
            Volver
          </span>
        </button>
      </div>


      <!-- CENTER -->
      <div
        class="min-w-[260px]
               max-w-[420px]
               px-8
               text-center"
      >
        <p
          class="truncate
                 text-sm
                 font-semibold
                 tracking-[-0.01em]
                 text-[#252525]"
        >
          {{
            song.title ||
            'Sin reproducción'
          }}
        </p>

        <p
          class="mt-0.5
                 truncate
                 text-[10px]
                 text-black/35"
        >
          {{ song.artist || '—' }}
        </p>
      </div>


      <!-- RIGHT -->
      <div
        class="flex
               justify-end"
      >
        <div
          class="flex
                 items-center
                 gap-1
                 rounded-[16px]
                 border
                 border-black/[0.05]
                 bg-[#f0eeeb]/80
                 p-1"
        >
          <!-- QUEUE -->
          <button
            class="group
                   flex
                   h-9 w-9
                   items-center
                   justify-center
                   rounded-[12px]
                   text-black/45
                   transition
                   hover:bg-white
                   hover:text-[#be5c2b]
                   hover:shadow-sm
                   active:scale-95"
            title="Ver cola"
            @click="
              navigate('queue')
            "
          >
            <ListMusic
              class="h-[18px] w-[18px]"
              :stroke-width="1.8"
            />
          </button>


          <!-- EDIT -->
          <button
            class="group
                   flex
                   h-9 w-9
                   items-center
                   justify-center
                   rounded-[12px]
                   text-black/45
                   transition
                   hover:bg-white
                   hover:text-[#be5c2b]
                   hover:shadow-sm
                   active:scale-95"
            title="Editar letra"
            @click="
              navigate(
                'lyrics-edit'
              )
            "
          >
            <Pencil
              class="h-[17px] w-[17px]"
              :stroke-width="1.8"
            />
          </button>
        </div>
      </div>
    </header>

<!-- LYRICS -->
    <section
      class="relative
         flex flex-1
         flex-col
         items-center
         px-3
         pb-20
         pt-[18vh]
         text-center
         lg:pt-[30vh]"
    >
      <!-- FADE SUPERIOR -->
      <div
        class="pointer-events-none
               fixed
               left-[var(--castillo-sidebar-width)]
               right-[330px]
               top-[65px]
               z-10
               hidden
               h-28
               bg-gradient-to-b
               from-[#faf9f7]
               via-[#faf9f7]/80
               to-transparent
               xl:block"
      />

      <!-- FADE INFERIOR -->
      <div
        class="pointer-events-none
               fixed
               bottom-[112px]
               left-[var(--castillo-sidebar-width)]
               right-[330px]
               z-10
               hidden
               h-28
               bg-gradient-to-t
               from-[#faf9f7]
               via-[#faf9f7]/80
               to-transparent
               xl:block"
      />
      <div
        v-if="loadingLyrics"
        class="text-sm
               text-black/35"
      >
        Cargando letras…
      </div>


      <div
        v-else-if="lyricsError"
        class="flex
               flex-col
               items-center"
      >
        <div
          class="flex h-20 w-20
                 items-center
                 justify-center
                 rounded-[28px]
                 bg-[#f1eeeb]"
        >
          <Music2
            class="h-8 w-8
                   text-black/20"
          />
        </div>

        <p
          class="mt-5
                 text-sm
                 text-black/40"
        >
          {{ lyricsError }}
        </p>
      </div>


      <div
        v-else
        class="w-full
               space-y-9
               pb-[35vh]"
      >
        <!-- LRC INFO -->
        <div
          v-if="
            credits.length ||
            metadata.by
          "
          class="mx-auto
                 mb-16
                 max-w-xl"
        >
          <div
            class="rounded-[26px]
                   bg-black/[0.025]
                   px-6 py-5"
          >
            <p
              class="text-[9px]
                     font-medium
                     uppercase
                     tracking-[0.18em]
                     text-black/25"
            >
              Información de la letra
            </p>


            <div
              v-if="credits.length"
              class="mt-4
                     space-y-2"
            >
              <div
                v-for="item in credits"
                :key="
                  `${item.type}-${item.value}`
                "
                class="grid
                       grid-cols-[90px_1fr]
                       gap-4
                       text-left"
              >
                <span
                  class="text-[11px]
                         text-black/30"
                >
                  {{ item.label }}
                </span>

                <span
                  class="text-[11px]
                         font-medium
                         text-black/55"
                >
                  {{ item.value }}
                </span>
              </div>
            </div>


            <div
              v-if="metadata.by"
              class="mt-3
                     grid
                     grid-cols-[90px_1fr]
                     gap-4
                     text-left"
            >
              <span
                class="text-[11px]
                       text-black/30"
              >
                LRC
              </span>

              <span
                class="text-[11px]
                       font-medium
                       text-black/55"
              >
                {{ metadata.by }}
              </span>
            </div>
          </div>
        </div>
        <button
          v-for="(line, index) in lyrics"
          :key="
            `${index}-${line.time}`
          "
          :ref="
            element => {
              if (element) {
                lineElements[index] =
                  element
              }
            }
          "
          class="group block
                 w-full
                 cursor-pointer
                 rounded-[26px]
                 px-4 py-3
                 transition-all
                 duration-500
                 ease-out
                 hover:bg-black/[0.025]"
          :class="
            lyricLineClass(index)
          "
          @click="
            seekToLyric(
              line,
              index
            )
          "
        >
          <span
            class="mb-1 block
                   text-[9px]
                   font-normal
                   tabular-nums
                   opacity-0
                   transition
                   group-hover:opacity-100"
          >
            {{ formatTime(line.time) }}
          </span>
          <span
            class="block
                   font-semibold
                   leading-[1.4]
                   tracking-[-0.02em]"
          >
            {{ line.text || '♪' }}
          </span>
        </button>
      </div>
    </section>


    <!-- SMALL PROGRESS -->
    <footer
      class="fixed
             bottom-[112px]
             left-[var(--castillo-sidebar-width)]
             right-[330px]
             z-20
             hidden
             border-t
             border-black/5
             bg-[#faf9f7]/90
             px-8 py-3
             backdrop-blur-xl
             transition-[left]
             duration-300
             xl:block"
    >
      <div
        class="flex items-center
               gap-3"
      >
        <span
          class="w-10
                 text-right
                 text-[10px]
                 text-[#ff6470]"
        >
          {{ formatTime(elapsed) }}
        </span>

        <WaveformProgress
          class="min-w-0 flex-1"
          :file="song.file || ''"
          :current="elapsed"
          :duration="currentDuration"
          :bars="220"
          :visual-bars="200"
          :height="26"
          active-color="#ff6470"
          inactive-color="#dce0e5"
          @seek="seek"
        />

        <span
          class="w-10
                 text-[10px]
                 text-black/25"
        >
          {{
            formatTime(
              currentDuration
            )
          }}
        </span>
      </div>
    </footer>

  </div>
</template>