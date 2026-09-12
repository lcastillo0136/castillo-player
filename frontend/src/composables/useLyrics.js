import {
  computed,
  ref,
  watch
} from 'vue'

import {
  useCastilloApi
} from './useCastilloApi'

const lyrics = ref([])
const credits = ref([])
const metadata = ref({})
const rawLyrics = ref('')

const lyricsMtime = ref(null)
const lyricsSize = ref(null)

const loadingLyrics = ref(false)
const lyricsError = ref('')
const loadedFile = ref('')

const {
  song,
  elapsed
} = useCastilloApi()


function fractionToSeconds(value) {
  if (!value) {
    return 0
  }

  if (value.length === 1) {
    return Number(value) / 10
  }

  if (value.length === 2) {
    return Number(value) / 100
  }

  return Number(
    value.slice(0, 3)
  ) / 1000
}

function parseLrc(content) {
  const parsedLyrics = []
  const parsedCredits = []
  const parsedMetadata = {}

  let offsetMs = 0

  const timestamp =
    /\[(\d{1,3}):(\d{1,2})(?:[.:](\d{1,3}))?\]/g


  /*
   * Metadatos LRC estándar:
   *
   * [ti:Título]
   * [ar:Artista]
   * [al:Álbum]
   * [by:Creador]
   * [offset:250]
   */
  const metadataPattern =
    /^\[(ti|ar|al|by|offset|re|ve):\s*(.*?)\]\s*$/i


  /*
   * Créditos que algunas fuentes
   * escriben como líneas sincronizadas.
   */
  const creditPatterns = [
    {
      type: 'lyrics',
      label: 'Letra',
      pattern:
        /^(?:作词|作詞|詞|lyrics?|written\s+by)\s*[:：]\s*(.+)$/i
    },

    {
      type: 'composer',
      label: 'Composición',
      pattern:
        /^(?:作曲|composer|composed\s+by)\s*[:：]\s*(.+)$/i
    },

    {
      type: 'arranger',
      label: 'Arreglos',
      pattern:
        /^(?:编曲|編曲|arranger|arranged\s+by)\s*[:：]\s*(.+)$/i
    },

    {
      type: 'translation',
      label: 'Traducción',
      pattern:
        /^(?:翻译|翻譯|translation|translated\s+by)\s*[:：]\s*(.+)$/i
    }
  ]


  /*
   * Primer pase:
   * detectar offset y metadatos.
   */
  for (
    const originalLine
    of content.split(/\r?\n/)
  ) {
    const line =
      originalLine.trim()

    const metadataMatch =
      line.match(metadataPattern)

    if (!metadataMatch) {
      continue
    }

    const key =
      metadataMatch[1]
        .toLowerCase()

    const value =
      metadataMatch[2]
        .trim()

    if (key === 'offset') {
      const parsed =
        Number.parseInt(
          value,
          10
        )

      if (
        Number.isFinite(parsed)
      ) {
        offsetMs = parsed
      }

      continue
    }

    parsedMetadata[key] = value
  }


  /*
   * Segundo pase:
   * timestamps + texto.
   */
  for (
    const originalLine
    of content.split(/\r?\n/)
  ) {
    const matches = [
      ...originalLine.matchAll(
        timestamp
      )
    ]

    if (!matches.length) {
      continue
    }


    const text =
      originalLine
        .replace(timestamp, '')
        .replace(
          /\\(['"])/g,
          '$1'
        )
        .trim()


    /*
     * Detectar créditos.
     */
    let credit = null

    for (
      const definition
      of creditPatterns
    ) {
      const match =
        text.match(
          definition.pattern
        )

      if (match) {
        credit = {
          type:
            definition.type,

          label:
            definition.label,

          value:
            match[1].trim()
        }

        break
      }
    }


    if (credit) {
      const duplicate =
        parsedCredits.some(
          item =>
            item.type ===
              credit.type &&
            item.value ===
              credit.value
        )

      if (!duplicate) {
        parsedCredits.push(
          credit
        )
      }

      continue
    }


    /*
     * Una línea vacía puede existir
     * como marcador temporal, pero no
     * necesitamos mostrarla como verso.
     */
    if (!text) {
      continue
    }


    for (const match of matches) {
      let time =
        Number(match[1]) * 60 +
        Number(match[2]) +
        fractionToSeconds(
          match[3]
        )


      /*
       * [offset:] está expresado
       * en milisegundos.
       */
      time +=
        offsetMs / 1000


      parsedLyrics.push({
        time:
          Math.max(
            0,
            time
          ),

        text
      })
    }
  }


  parsedLyrics.sort(
    (a, b) =>
      a.time - b.time
  )


  return {
    lyrics:
      parsedLyrics,

    credits:
      parsedCredits,

    metadata:
      parsedMetadata
  }
}

async function loadLyrics(file, force = false) {
  lyrics.value = []
	credits.value = []
	metadata.value = {}

	lyricsError.value = ''

  rawLyrics.value = ''

  lyricsMtime.value = null
  lyricsSize.value = null

  if (!file) {
    loadedFile.value = ''
    return
  }

  loadingLyrics.value = true

  try {
    const params =
      new URLSearchParams({
        file
      })

    const response = await fetch(
      `/castillo-api/lyrics.php?${params.toString()}`,
      {
        cache: 'no-store'
      }
    )

    const data =
      await response.json()

    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer las letras.'
      )
    }

    loadedFile.value = file

    if (!data.exists) {
      lyricsError.value =
        'Esta canción no tiene letras sincronizadas.'

      return
    }

    const parsed =
		  parseLrc(
		    data.content || ''
		  )

		lyrics.value =
		  parsed.lyrics

		credits.value =
		  parsed.credits

		metadata.value =
		  parsed.metadata

    rawLyrics.value =
      data.content || ''

    lyricsMtime.value =
      data.exists
        ? Number(data.mtime)
        : -1

    lyricsSize.value =
      data.exists
        ? Number(data.size)
        : -1

    if (!lyrics.value.length) {
      lyricsError.value =
        'El archivo LRC no contiene líneas sincronizadas.'
    }

  } catch (error) {
    lyricsError.value =
      error.message
  } finally {
    loadingLyrics.value = false
  }
}


const activeIndex = computed(() => {
  if (!lyrics.value.length) {
    return -1
  }

  let result = -1

  for (
    let index = 0;
    index < lyrics.value.length;
    index++
  ) {
    if (
      lyrics.value[index].time
      <= elapsed.value
    ) {
      result = index
    } else {
      break
    }
  }

  return result
})


const currentLine = computed(() => {
  if (activeIndex.value < 0) {
    return null
  }

  return lyrics.value[
    activeIndex.value
  ]
})


watch(
  () => song.value.file,

  file => {
    if (
      file &&
      file !== loadedFile.value
    ) {
      loadLyrics(file)
    }
  },

  {
    immediate: true
  }
)


export function useLyrics() {
  return {
    lyrics,
    credits,
    metadata,

    rawLyrics,
    lyricsMtime,
    lyricsSize,

    loadingLyrics,
    lyricsError,
    loadedFile,

    activeIndex,
    currentLine,

    loadLyrics
  }
}