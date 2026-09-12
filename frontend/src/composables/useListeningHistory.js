import {
  ref,
  watch
} from 'vue'

import {
  useCastilloApi
} from './useCastilloApi'


const history = ref([])
const historyLoading = ref(false)
const historyLoaded = ref(false)

let started = false


async function loadHistory() {
  historyLoading.value = true

  try {
    const response =
      await fetch(
        '/castillo-api/history.php',
        {
          cache: 'no-store'
        }
      )

    const data =
      await response.json()

    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible cargar el historial.'
      )
    }

    history.value =
      Array.isArray(data.items)
        ? data.items
        : []

    historyLoaded.value = true

  } catch (error) {
    console.error(
      '[Castillo history]',
      error
    )
  } finally {
    historyLoading.value = false
  }
}


async function recordCurrentSong() {
  try {
    const response =
      await fetch(
        '/castillo-api/history.php',
        {
          method: 'POST'
        }
      )

    const data =
      await response.json()

    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible registrar la reproducción.'
      )
    }

    history.value =
      Array.isArray(data.items)
        ? data.items
        : []

  } catch (error) {
    console.error(
      '[Castillo history]',
      error
    )
  }
}


function startHistoryTracking() {
  if (started) {
    return
  }

  started = true

  const {
    song
  } = useCastilloApi()


  loadHistory()


  watch(
    () => song.value?.file,

    (
      file,
      previousFile
    ) => {
      if (
        !file ||
        file === previousFile
      ) {
        return
      }

      recordCurrentSong()
    },

    {
      immediate: true
    }
  )
}


export function useListeningHistory() {
  startHistoryTracking()

  return {
    history,
    historyLoading,
    historyLoaded,

    loadHistory,
    recordCurrentSong
  }
}