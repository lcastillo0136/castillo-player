import {
  computed,
  ref
} from 'vue'


/*
 * Estado a nivel de módulo.
 *
 * Al estar fuera de useNasSync(), estos refs
 * son compartidos por todos los componentes
 * de Castillo Player.
 */
const syncing = ref(false)

const syncResult = ref(null)
const syncError = ref('')

const syncingFiles = ref([])

const startedAt = ref(null)
const finishedAt = ref(null)

let resultTimer = null


const syncingCount = computed(
  () =>
    syncingFiles.value.length
)


function clearResultTimer() {
  if (resultTimer) {
    window.clearTimeout(
      resultTimer
    )

    resultTimer = null
  }
}


function clearSyncResult() {
  clearResultTimer()

  syncResult.value = null
  syncError.value = ''
}


function scheduleResultClear() {
  clearResultTimer()

  resultTimer =
    window.setTimeout(
      () => {
        syncResult.value = null
      },
      8000
    )
}


async function syncFiles(
  files
) {
  const selected =
    Array.from(
      new Set(
        (
          Array.isArray(files)
            ? files
            : []
        )
          .filter(
            file =>
              typeof file ===
                'string' &&
              file.trim()
          )
          .map(
            file =>
              file.trim()
          )
      )
    )


  if (!selected.length) {
    throw new Error(
      'No hay archivos seleccionados.'
    )
  }


  /*
   * Impide iniciar otra sincronización
   * desde cualquier parte de la SPA.
   */
  if (syncing.value) {
    throw new Error(
      'Ya hay una sincronización con el NAS en curso.'
    )
  }


  clearResultTimer()

  syncing.value = true

  syncingFiles.value =
    selected

  startedAt.value =
    Date.now()

  finishedAt.value =
    null

  syncResult.value =
    null

  syncError.value =
    ''


  try {
    const response =
      await fetch(
        '/castillo-api/pending.php',
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify({
              action:
                'sync',

              files:
                selected
            })
        }
      )


    let data = {}


    try {
      data =
        await response.json()

    } catch {
      throw new Error(
        'El servidor devolvió una respuesta inválida.'
      )
    }


    if (!response.ok) {
      const exception =
        new Error(
          data.error ||
          'No fue posible sincronizar con el NAS.'
        )


      /*
       * Conservamos la respuesta completa
       * para que PendingChangesView pueda
       * mostrar conflictos.
       */
      exception.data =
        data


      throw exception
    }


    syncResult.value =
      data

    finishedAt.value =
      Date.now()


    scheduleResultClear()


    return data


  } catch (exception) {
    syncError.value =
      exception?.message ||
      'No fue posible sincronizar con el NAS.'

    finishedAt.value =
      Date.now()


    throw exception

  } finally {
    syncing.value =
      false

    syncingFiles.value =
      []
  }
}


export function useNasSync() {
  return {
    syncing,
    syncingCount,
    syncingFiles,

    syncResult,
    syncError,

    startedAt,
    finishedAt,

    syncFiles,

    clearSyncResult
  }
}