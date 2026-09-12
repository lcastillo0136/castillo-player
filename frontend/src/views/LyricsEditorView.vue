<script setup>
import {
  computed,
  nextTick,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  Check,
  Clock3,
  Minus,
  Pause,
  Play,
  Plus,
  Save,
  ChevronDown,
  ChevronUp,
  ClipboardPaste,
  TimerOff,
  Trash2,
  X
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

import {
  useLyrics
} from '../composables/useLyrics'

import WaveformProgress
  from '../components/WaveformProgress.vue'

const {
  song,
  elapsed,
  currentDuration,
  playing,

  togglePlayback,
  seek
} = useCastilloApi()

const {
  lyrics,
  rawLyrics,
  lyricsMtime,
  lyricsSize,
  loadLyrics
} = useLyrics()

const {
  goBack
} = useCastilloNavigation()

const editorLines = ref([])
const selectedIndex = ref(-1)
const showPasteLyrics = ref(false)
const pastedLyricsText = ref('')

const lineElements = ref([])

const saving = ref(false)
const saveError = ref('')
const saved = ref(false)

let editorLineId = 0


function createEditorLine({
  time = null,
  text = '',
  changed = false
} = {}) {
  editorLineId += 1


  const hasTime =
    time !== null &&
    time !== undefined &&
    time !== '' &&
    Number.isFinite(
      Number(time)
    )


  return {
    id:
      editorLineId,

    time:
      hasTime
        ? Number(time)
        : null,

    text,

    changed
  }
}

watch(
  lyrics,
  value => {
    editorLines.value =
      value.map(line =>
        createEditorLine({
          time:
            Number(line.time),

          text:
            line.text,

          changed:
            false
        })
      )


    /*
     * Si la canción no tiene LRC,
     * empezamos con una línea vacía.
     */
    if (!editorLines.value.length) {
      editorLines.value = [
        createEditorLine()
      ]
    }


    selectedIndex.value = 0
  },
  {
    immediate: true,
    deep: true
  }
)

const selectedLine = computed(() => {
  if (selectedIndex.value < 0) {
    return null
  }

  return (
    editorLines.value[
      selectedIndex.value
    ] || null
  )
})

const unsyncedCount = computed(() => {
  return editorLines.value.filter(
    line =>
      String(
        line.text || ''
      ).trim() &&
      (
        line.time === null ||
        !Number.isFinite(
          Number(line.time)
        )
      )
  ).length
})

const pastedLineCount = computed(() => {
  return pastedLyricsText.value
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .split('\n')
    .map(line =>
      line.trim()
    )
    .filter(Boolean)
    .length
})

function formatTimestamp(value) {
  if (
    value === null ||
    value === undefined ||
    !Number.isFinite(
      Number(value)
    )
  ) {
    return '--:--.--'
  }

  const seconds =
    Math.max(
      0,
      Number(value)
    )

  const minutes =
    Math.floor(
      seconds / 60
    )

  const remaining =
    seconds -
    minutes * 60

  return (
    String(minutes)
      .padStart(2, '0') +
    ':' +
    remaining
      .toFixed(2)
      .padStart(5, '0')
  )
}

function selectLine(index) {
  selectedIndex.value =
    index

  const line =
    editorLines.value[index]

  if (!line) {
    return
  }


  if (
    line.time === null ||
    !Number.isFinite(
      Number(line.time)
    )
  ) {
    return
  }


  seek(
    Number(line.time)
  )
}

function addLineAfter(
  index = selectedIndex.value
) {
  const newLine =
    createEditorLine({
      changed: true
    })


  const insertAt =
    Math.max(
      0,
      Math.min(
        editorLines.value.length,
        index + 1
      )
    )


  editorLines.value.splice(
    insertAt,
    0,
    newLine
  )


  selectedIndex.value =
    insertAt


  nextTick(() => {
    lineElements.value[
      insertAt
    ]?.scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    })
  })
}

function removeLine(index) {
  if (
    index < 0 ||
    index >=
      editorLines.value.length
  ) {
    return
  }


  editorLines.value.splice(
    index,
    1
  )


  /*
   * Siempre dejamos al menos
   * una línea editable.
   */
  if (!editorLines.value.length) {
    editorLines.value.push(
      createEditorLine({
        changed: true
      })
    )
  }


  selectedIndex.value =
    Math.min(
      index,
      editorLines.value.length - 1
    )
}

function moveLine(
  index,
  direction
) {
  const target =
    index + direction


  if (
    target < 0 ||
    target >=
      editorLines.value.length
  ) {
    return
  }


  const [line] =
    editorLines.value.splice(
      index,
      1
    )


  editorLines.value.splice(
    target,
    0,
    line
  )


  line.changed = true

  selectedIndex.value =
    target


  nextTick(() => {
    lineElements.value[
      target
    ]?.scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    })
  })
}

function clearTimestamp(index) {
  const line =
    editorLines.value[index]

  if (!line) {
    return
  }

  line.time = null
  line.changed = true

  selectedIndex.value =
    index
}

async function markCurrentTime() {
  const line =
    selectedLine.value

  if (!line) {
    return
  }


  line.time =
    Number(
      elapsed.value
    )

  line.changed = true


  if (
    selectedIndex.value <
    editorLines.value.length - 1
  ) {
    selectedIndex.value += 1

    await nextTick()

    lineElements.value[
      selectedIndex.value
    ]?.scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    })
  }
}

function adjustSelectedTime(amount) {
  const line =
    selectedLine.value

  if (!line) {
    return
  }


  /*
   * Si todavía no tiene timestamp,
   * usamos el tiempo actual.
   */
  if (
    line.time === null ||
    !Number.isFinite(
      Number(line.time)
    )
  ) {
    line.time =
      Math.max(
        0,
        Number(elapsed.value) +
        amount
      )

  } else {
    line.time =
      Math.max(
        0,
        Number(line.time) +
        amount
      )
  }


  line.changed = true

  seek(
    line.time
  )
}

function updateText(
  index,
  value
) {
  const line =
    editorLines.value[index]

  if (!line) {
    return
  }

  line.text = value
  line.changed = true
}

async function saveLyrics() {
  if (hasUnsyncedLines()) {
    saveError.value =
      'Hay líneas sin timestamp. Sincronízalas antes de guardar.'

    return
  }

  if (
    !song.value?.file ||
    saving.value
  ) {
    return
  }


  saving.value = true
  saveError.value = ''
  saved.value = false


  try {
    const content =
      buildLrcContent()


    const response =
      await fetch(
        '/castillo-api/save-lyrics.php',
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify({
              file:
                song.value.file,

              content,

              expected_mtime:
                lyricsMtime.value
                ?? -1,

              expected_size:
                lyricsSize.value
                ?? -1
            })
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible guardar la letra.'
      )
    }


    /*
     * Volvemos a cargar desde disco,
     * no desde la copia en memoria.
     */
    await loadLyrics(
      song.value.file,
      true
    )


    editorLines.value.forEach(
      line => {
        line.changed = false
      }
    )


    saved.value = true


    window.setTimeout(
      () => {
        saved.value = false
      },
      1800
    )


  } catch (error) {
    saveError.value =
      error.message ||
      'No fue posible guardar la letra.'

  } finally {
    saving.value = false
  }
}

function formatLrcTimestamp(
  seconds
) {
  const totalCentiseconds =
    Math.max(
      0,
      Math.round(
        Number(seconds || 0) *
        100
      )
    )


  const minutes =
    Math.floor(
      totalCentiseconds /
      6000
    )


  const secondsPart =
    (
      totalCentiseconds %
      6000
    ) /
    100


  return (
    '[' +
    String(minutes)
      .padStart(2, '0') +
    ':' +
    secondsPart
      .toFixed(2)
      .padStart(5, '0') +
    ']'
  )
}

function preservedLrcLines() {
  const raw =
    rawLyrics.value || ''


  const hasTimestamp =
    /\[\d{1,3}:\d{1,2}(?:[.:]\d{1,3})?\]/


  const creditPattern =
    /^(?:作词|作詞|詞|lyrics?|written\s+by|作曲|composer|composed\s+by|编曲|編曲|arranger|arranged\s+by|翻译|翻譯|translation|translated\s+by)\s*[:：]/i


  return raw
    .split(/\r?\n/)
    .filter(original => {
      const line =
        original.trim()

      if (!line) {
        return false
      }


      /*
       * Metadatos, comentarios y otras
       * líneas sin timestamp se preservan.
       */
      if (
        !hasTimestamp.test(line)
      ) {
        return true
      }


      /*
       * También preservamos los créditos
       * sincronizados originales.
       */
      const text =
        line
          .replace(
            /\[\d{1,3}:\d{1,2}(?:[.:]\d{1,3})?\]/g,
            ''
          )
          .trim()


      return creditPattern.test(
        text
      )
    })
}

function buildLrcContent() {
  const preserved =
    preservedLrcLines()


  const body =
    editorLines.value
      .filter(line =>
        String(
          line.text || ''
        ).trim()
      )
      .map(line =>
        formatLrcTimestamp(
          line.time
        ) +
        String(
          line.text || ''
        ).trim()
      )


  return [
    ...preserved,

    ...(preserved.length
      ? ['']
      : []),

    ...body,

    ''
  ].join('\n')
}

function hasUnsyncedLines() {
  return (
    unsyncedCount.value > 0
  )
}

async function importPastedLyrics() {
  if (
    !pastedLyricsText.value.trim()
  ) {
    return
  }

  await replaceLyricsFromText(
    pastedLyricsText.value
  )

  pastedLyricsText.value = ''
  showPasteLyrics.value = false
}

function parsePastedLyrics(rawText) {
  const source =
    String(rawText || '')
      .replace(/\r\n?/g, '\n')


  const result = []


  for (
    const originalLine
    of source.split('\n')
  ) {
    const line =
      originalLine.trim()


    /*
     * Ignoramos líneas completamente
     * vacías.
     */
    if (!line) {
      continue
    }


    /*
     * LRC:
     *
     * [00:04.20]Texto
     * [01:15.5]Texto
     * [02:10]Texto
     *
     * También admite ":" antes
     * de las fracciones.
     */
    const timestampRegex =
      /\[(\d{1,3}):(\d{1,2})(?:[.:](\d{1,3}))?\]/g


    const timestamps = [
      ...line.matchAll(
        timestampRegex
      )
    ]


    /*
     * Línea sin timestamp.
     */
    if (!timestamps.length) {
      result.push({
        time: null,
        text: line
      })

      continue
    }


    /*
     * Quitamos los timestamps del
     * principio/texto y conservamos
     * solamente la letra.
     */
    const text =
      line
        .replace(
          timestampRegex,
          ''
        )
        .trim()


    /*
     * Soportamos incluso LRC con
     * varios timestamps:
     *
     * [00:20.00][01:30.00]Coro
     */
    for (
      const match
      of timestamps
    ) {
      const minutes =
        Number(match[1])

      const seconds =
        Number(match[2])

      const fraction =
        match[3]
          ? Number(
              `0.${match[3]}`
            )
          : 0


      result.push({
        time:
          minutes * 60 +
          seconds +
          fraction,

        text
      })
    }
  }


  return result
}

async function replaceLyricsFromText(
  rawText
) {
  const imported =
    parsePastedLyrics(
      rawText
    )


  if (!imported.length) {
    saveError.value =
      'No se encontraron líneas para importar.'

    return
  }


  /*
   * IMPORTANTE:
   *
   * No usamos push().
   * No usamos splice para añadir.
   *
   * Sustituimos TODA la lista.
   */
  editorLines.value =
    imported.map(line =>
      createEditorLine({
        time:
          line.time,

        text:
          line.text,

        changed:
          true
      })
    )


  selectedIndex.value = 0

  lineElements.value = []

  saveError.value = ''


  await nextTick()


  lineElements.value[
    0
  ]?.scrollIntoView({
    behavior: 'smooth',
    block: 'center'
  })
}
</script>
<template>
  <div
    class="mx-auto
           flex
           min-h-full
           max-w-5xl
           flex-col
           pb-12
           pt-[76px]
           lg:pt-0"
  >
    <!-- MOBILE HEADER -->
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
               grid-cols-[44px_1fr_44px]
               items-center
               px-5"
      >
        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-black/[0.035]"
          @click="goBack"
        >
          <ArrowLeft
            class="h-5 w-5"
          />
        </button>


        <div class="text-center">
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
            class="text-[13px]
                   font-semibold"
          >
            Editor LRC
          </p>
        </div>


        <button
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-full
                 bg-[#ff6470]
                 text-white"
          @click="saveLyrics"
        >
          <Save
            class="h-4 w-4"
          />
        </button>
      </div>
    </header>


    <!-- DESKTOP HEADER -->
    <header
      class="hidden
             items-center
             justify-between
             border-b
             border-black/5
             py-4
             lg:flex"
    >
      <button
        class="flex
               items-center
               gap-2
               rounded-full
               px-3 py-2
               text-sm
               text-black/45
               hover:bg-black/5"
        @click="goBack"
      >
        <ArrowLeft
          class="h-4 w-4"
        />

        Letras
      </button>


      <div class="text-center">
        <p
          class="text-sm
                 font-semibold"
        >
          Editor LRC
        </p>

        <p
          class="mt-0.5
                 text-[10px]
                 text-black/35"
        >
          {{ song.title || '—' }}
        </p>
      </div>


      <button
        class="flex
               items-center
               gap-2
               rounded-full
               bg-[#252525]
               px-4 py-2
               text-xs
               font-semibold
               text-white"
        @click="saveLyrics"
      >
        <Check
          v-if="saved"
          class="h-4 w-4
                 text-[#f2f478]"
        />

        <Save
          v-else
          class="h-4 w-4"
        />

        <span>
          {{
            saving
              ? 'Guardando…'
              : saved
                ? 'Guardado'
                : 'Guardar'
          }}
        </span>
      </button>
    </header>


    <!-- SONG -->
    <section
      class="pt-6
             text-center
             lg:pt-8"
    >
      <h1
        class="truncate
               text-2xl
               font-semibold
               tracking-[-0.03em]"
      >
        {{
          song.title ||
          'Sin reproducción'
        }}
      </h1>

      <p
        class="mt-1
               text-sm
               text-black/40"
      >
        {{ song.artist || '—' }}

        <template
          v-if="song.album"
        >
          · {{ song.album }}
        </template>
      </p>


      <!-- CURRENT TIME -->
      <div
        class="mt-7
               flex
               items-center
               justify-center
               gap-2"
      >
        <Clock3
          class="h-4 w-4
                 text-[#be5c2b]"
        />

        <span
          class="font-mono
                 text-2xl
                 font-semibold
                 tabular-nums"
        >
          {{
            formatTimestamp(
              elapsed
            )
          }}
        </span>
      </div>


      <!-- WAVEFORM -->
      <div
        class="mx-auto
               mt-5
               max-w-2xl"
      >
        <WaveformProgress
          :file="song.file || ''"
          :current="elapsed"
          :duration="currentDuration"
          :bars="220"
          :visual-bars="64"
          :height="46"
          active-color="#ff6470"
          inactive-color="#dde1e7"
          @seek="seek"
        />
      </div>


      <button
        class="mx-auto
               mt-5
               flex
               h-14 w-14
               items-center
               justify-center
               rounded-full
               bg-[#ff6470]
               text-white
               shadow-lg"
        @click="togglePlayback"
      >
        <Pause
          v-if="playing"
          class="h-6 w-6"
        />

        <Play
          v-else
          class="ml-0.5
                 h-6 w-6"
        />
      </button>
    </section>


    <!-- EDITOR -->
    <section
      class="mt-8
             rounded-[28px]
             bg-[#f1eeeb]
             p-3
             sm:p-5"
    >
      <div
        class="mb-3
               flex
               items-center
               justify-between
               px-2"
      >
        <div>
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.16em]
                   text-black/30"
          >
            Líneas
          </p>

          <p
            class="mt-1
                   text-xs
                   text-black/35"
          >
            {{ editorLines.length }}
            líneas
          </p>
        </div>

        <div
          class="flex
                 items-center
                 gap-2"
        >
          <!-- PEGAR LETRA -->
          <button
            class="flex
                   items-center
                   gap-2
                   rounded-full
                   bg-white
                   px-4 py-2
                   text-xs
                   font-semibold
                   text-[#252525]
                   shadow-sm
                   transition
                   hover:shadow-md"
            @click="
              showPasteLyrics = true
            "
          >
            <ClipboardPaste
              class="h-4 w-4
                     text-[#be5c2b]"
            />

            Pegar letra
          </button>


          <!-- AÑADIR UNA LÍNEA -->
          <button
            class="flex
                   items-center
                   gap-2
                   rounded-full
                   bg-white
                   px-4 py-2
                   text-xs
                   font-semibold
                   text-[#252525]
                   shadow-sm
                   transition
                   hover:shadow-md"
            @click="
              addLineAfter()
            "
          >
            <Plus
              class="h-4 w-4
                     text-[#be5c2b]"
            />

            Añadir línea
          </button>
        </div>
      </div>

      <div
        v-if="unsyncedCount"
        class="mb-3
               rounded-2xl
               bg-[#fff7e8]
               px-4 py-3
               text-xs
               text-[#9a651a]"
      >
        {{ unsyncedCount }}
        {{
          unsyncedCount === 1
            ? 'línea todavía no tiene timestamp.'
            : 'líneas todavía no tienen timestamp.'
        }}

        Usa
        <strong>Marcar tiempo</strong>
        para sincronizarlas.
      </div>

      <div
        class="castillo-scroll
               max-h-[45vh]
               space-y-2
               overflow-y-auto
               pr-1"
      >
        <button
          v-for="
            (line, index)
            in editorLines
          "
          :key="line.id"
          :ref="
            element => {
              if (element) {
                lineElements[index] =
                  element
              }
            }
          "
          class="grid
                 w-full
                 grid-cols-[72px_minmax(0,1fr)_auto]
                 items-center
                 gap-3
                 rounded-[18px]
                 px-3 py-3
                 text-left
                 transition"
          :class="
            selectedIndex === index
              ? 'bg-white shadow-sm'
              : 'hover:bg-white/50'
          "
          @click="
            selectLine(index)
          "
        >
          <span
            class="font-mono
                   text-[10px]
                   tabular-nums"
            :class="
              line.time === null
              ? 'text-[#be5c2b]'
              : selectedIndex === index
                ? 'text-[#ff6470]'
                : 'text-black/30'
            "
          >
            {{
              formatTimestamp(
                line.time
              )
            }}
          </span>


          <input
            :value="line.text"
            class="min-w-0
                   bg-transparent
                   text-sm
                   font-medium
                   outline-none"
            @click.stop="
              selectedIndex = index
            "
            @input="
              updateText(
                index,
                $event.target.value
              )
            "
          />
          <div
            class="flex
                   items-center
                   gap-1"
            @click.stop
          >
            <!-- MOVE UP -->
            <button
              class="flex
                     h-8 w-8
                     items-center
                     justify-center
                     rounded-lg
                     text-black/25
                     transition
                     hover:bg-black/[0.05]
                     hover:text-black/60
                     disabled:opacity-20"
              :disabled="index === 0"
              title="Mover arriba"
              @click="
                moveLine(
                  index,
                  -1
                )
              "
            >
              <ChevronUp
                class="h-4 w-4"
              />
            </button>


            <!-- MOVE DOWN -->
            <button
              class="flex
                     h-8 w-8
                     items-center
                     justify-center
                     rounded-lg
                     text-black/25
                     transition
                     hover:bg-black/[0.05]
                     hover:text-black/60
                     disabled:opacity-20"
              :disabled="
                index ===
                editorLines.length - 1
              "
              title="Mover abajo"
              @click="
                moveLine(
                  index,
                  1
                )
              "
            >
              <ChevronDown
                class="h-4 w-4"
              />
            </button>


            <!-- CLEAR TIMESTAMP -->
            <button
              class="flex
                     h-8 w-8
                     items-center
                     justify-center
                     rounded-lg
                     text-black/25
                     transition
                     hover:bg-[#fff1ec]
                     hover:text-[#be5c2b]"
              title="Quitar timestamp"
              @click="
                clearTimestamp(
                  index
                )
              "
            >
              <TimerOff
                class="h-4 w-4"
              />
            </button>


            <!-- DELETE -->
            <button
              class="flex
                     h-8 w-8
                     items-center
                     justify-center
                     rounded-lg
                     text-black/25
                     transition
                     hover:bg-red-50
                     hover:text-red-500"
              title="Eliminar línea"
              @click="
                removeLine(
                  index
                )
              "
            >
              <Trash2
                class="h-4 w-4"
              />
            </button>
          </div>
        </button>
      </div>
    </section>

    <p
      v-if="saveError"
      class="mt-3
             rounded-2xl
             bg-red-50
             px-4 py-3
             text-xs
             text-red-600"
    >
      {{ saveError }}
    </p>

    <!-- TIMESTAMP CONTROLS -->
    <section
      class="sticky
             bottom-4
             z-30
             mx-auto
             mt-6
             flex
             items-center
             gap-3
             rounded-full
             border
             border-black/[0.05]
             bg-white/95
             p-2
             shadow-[0_16px_50px_rgba(0,0,0,.12)]
             backdrop-blur-xl"
    >
      <button
        class="flex
               h-11
               items-center
               gap-1
               rounded-full
               px-4
               text-xs
               font-semibold
               text-black/45
               hover:bg-black/[0.04]"
        @click="
          adjustSelectedTime(
            -0.10
          )
        "
      >
        <Minus
          class="h-4 w-4"
        />

        0.10
      </button>


      <button
        class="flex
               h-12
               items-center
               gap-2
               rounded-full
               bg-[#252525]
               px-6
               text-xs
               font-semibold
               text-white
               shadow-lg"
        @click="markCurrentTime"
      >
        <Check
          class="h-4 w-4
                 text-[#f2f478]"
        />

        Marcar tiempo
      </button>


      <button
        class="flex
               h-11
               items-center
               gap-1
               rounded-full
               px-4
               text-xs
               font-semibold
               text-black/45
               hover:bg-black/[0.04]"
        @click="
          adjustSelectedTime(
            0.10
          )
        "
      >
        <Plus
          class="h-4 w-4"
        />

        0.10
      </button>
    </section>
    <!-- PEGAR LETRA -->
    <div
      v-if="showPasteLyrics"
      class="fixed
             inset-0
             z-[500]
             flex
             items-center
             justify-center
             bg-black/35
             px-4
             backdrop-blur-sm"
      @click.self="
        showPasteLyrics = false
      "
    >
      <div
        class="w-full
               max-w-2xl
               rounded-[28px]
               bg-[#faf9f7]
               p-5
               shadow-2xl
               sm:p-6"
      >
        <!-- HEADER -->
        <div
          class="flex
                 items-start
                 justify-between
                 gap-4"
        >
          <div>
            <p
              class="text-lg
                     font-semibold
                     tracking-[-0.02em]"
            >
              Pegar letra
            </p>

            <p
              class="mt-1
                     text-xs
                     leading-relaxed
                     text-black/40"
            >
              Cada salto de línea se convertirá
              en una línea independiente.
            </p>
          </div>


          <button
            class="flex
                   h-9 w-9
                   shrink-0
                   items-center
                   justify-center
                   rounded-full
                   bg-black/[0.04]
                   text-black/40
                   transition
                   hover:bg-black/[0.08]"
            @click="
              showPasteLyrics = false
            "
          >
            <X
              class="h-4 w-4"
            />
          </button>
        </div>


        <!-- TEXTAREA -->
        <textarea
          v-model="pastedLyricsText"
          rows="14"
          autofocus
          placeholder="Pega aquí la letra completa...

    Primera línea
    Segunda línea
    Tercera línea"
          class="mt-5
                 w-full
                 resize-y
                 rounded-[20px]
                 border
                 border-black/[0.06]
                 bg-white
                 px-4 py-4
                 text-sm
                 leading-7
                 outline-none
                 transition
                 placeholder:text-black/20
                 focus:border-[#be5c2b]/40
                 focus:ring-4
                 focus:ring-[#be5c2b]/10"
        />


        <!-- FOOTER -->
        <div
          class="mt-4
                 flex
                 flex-col
                 gap-3
                 sm:flex-row
                 sm:items-center
                 sm:justify-between"
        >
          <p
            class="text-xs
                   text-black/35"
          >
            {{
              pastedLineCount
            }}
            {{
              pastedLineCount === 1
                ? 'línea detectada'
                : 'líneas detectadas'
            }}
          </p>


          <div
            class="flex
                   items-center
                   justify-end
                   gap-2"
          >
            <button
              class="rounded-full
                     px-4 py-2.5
                     text-xs
                     font-semibold
                     text-black/40
                     hover:bg-black/[0.04]"
              @click="
                showPasteLyrics = false
              "
            >
              Cancelar
            </button>


            <button
              class="rounded-full
                     bg-[#252525]
                     px-5 py-2.5
                     text-xs
                     font-semibold
                     text-white
                     transition
                     disabled:cursor-not-allowed
                     disabled:opacity-30"
              :disabled="
                pastedLineCount === 0
              "
              @click="
                importPastedLyrics
              "
            >
              Reemplazar letras
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>