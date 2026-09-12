import {
  computed,
  ref
} from 'vue'

const status = ref({})
const song = ref({})
const queue = ref([])

const library = ref({
  songs: [],
  albums: [],
  artists: [],
  total: 0
})

const favorites = ref([])
const playlists = ref({})

const hashtags = ref([])
const hashtagStats = ref({})
const loadingHashtags = ref(false)
const hashtagsError = ref('')

const elapsed = ref(0)

const loadingStatus = ref(false)
const loadingLibrary = ref(false)

const error = ref('')

const serverOnline = ref(true)
const reconnecting = ref(false)

let offlineTimer = null
let clockTimer = null
let eventSource = null

/*
 * MPD puede entregar bitrate "0" justo al cambiar
 * de pista y calcular el valor real unos instantes
 * después sin generar otro evento idle.
 *
 * Este timer NO es polling: solo hace una consulta
 * puntual cuando recibimos un bitrate todavía no
 * disponible.
 */
let bitrateRefreshTimer = null
let bitrateRefreshAttempt = 0

let lastElapsed = 0
let lastSyncClock = performance.now()

/*
 * Metadata editada desde Castillo.
 *
 * MPD puede conservar en la cola los tags
 * antiguos aunque el archivo ya haya sido
 * modificado.
 *
 * Guardamos un override temporal por file
 * para que SSE no vuelva a mostrar los
 * metadatos anteriores.
 */
const tagOverrides = ref({})

async function request(action, data = null) {
  let response

  if (data === null) {
    const params = new URLSearchParams({
      action
    })

    response = await fetch(
      `/castillo-api/api.php?${params.toString()}`,
      {
        cache: 'no-store'
      }
    )
  } else {
    response = await fetch(
      '/castillo-api/api.php',
      {
        method: 'POST',

        headers: {
          'Content-Type': 'application/json'
        },

        body: JSON.stringify({
          action,
          ...data
        })
      }
    )
  }

  let result

  try {
    result = await response.json()
  } catch {
    throw new Error(
      `Respuesta inválida del servidor (${response.status})`
    )
  }

  if (!response.ok) {
    throw new Error(
      result.error ||
      `Error HTTP ${response.status}`
    )
  }

  return result
}

function number(value) {
  const result = Number.parseFloat(value)

  return Number.isFinite(result)
    ? result
    : 0
}

function applyTagOverrideToSong(
  source
) {
  if (
    !source ||
    typeof source !== 'object'
  ) {
    return source || {}
  }


  const file =
    source.file || ''


  if (!file) {
    return source
  }


  const override =
    tagOverrides.value[file]


  if (!override) {
    return source
  }


  return {
    ...source,

    title:
      override.title ?? source.title,

    artist:
      override.artist ?? source.artist,

    album:
      override.album ?? source.album,

    albumartist:
      override.albumartist ??
      source.albumartist,

    date:
      override.date ?? source.date,

    genre:
      override.genre ?? source.genre,

    /*
     * Castillo/MPD puede llamar
     * al número de pista "track".
     */
    track:
      override.tracknumber ??
      source.track,

    tracknumber:
      override.tracknumber ??
      source.tracknumber
  }
}


function applyTagOverride(
  file,
  tags = {}
) {
  if (!file) {
    return
  }


  const override = {
    title:
      String(
        tags.title ?? ''
      ),

    artist:
      String(
        tags.artist ?? ''
      ),

    album:
      String(
        tags.album ?? ''
      ),

    albumartist:
      String(
        tags.albumartist ?? ''
      ),

    date:
      String(
        tags.date ?? ''
      ),

    tracknumber:
      String(
        tags.tracknumber ?? ''
      ),

    genre:
      String(
        tags.genre ?? ''
      )
  }


  tagOverrides.value = {
    ...tagOverrides.value,

    [file]:
      override
  }


  /*
   * Actualización inmediata de
   * "Ahora suena".
   */
  if (
    song.value?.file === file
  ) {
    song.value =
      applyTagOverrideToSong(
        song.value
      )
  }


  /*
   * La cola MPD también conserva
   * metadata antigua.
   */
  queue.value =
    queue.value.map(item =>
      applyTagOverrideToSong(
        item
      )
    )
}

async function loadRealFileTags(
  file,
  force = false
) {
  if (!file) {
    return
  }


  /*
   * Si ya leímos este archivo durante
   * esta sesión, no hacemos otra petición.
   *
   * SSE puede generar muchos eventos.
   */
  if (
    !force &&
    tagOverrides.value[file]
  ) {
    /*
     * MPD pudo volver a mandar sus tags
     * antiguos, así que reaplicamos
     * nuestra copia real.
     */
    if (
      song.value?.file === file
    ) {
      song.value =
        applyTagOverrideToSong(
          song.value
        )
    }

    return
  }


  try {
    const response =
      await fetch(
        '/castillo-api/tags.php?' +
        new URLSearchParams({
          file
        }),
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer los tags.'
      )
    }


    applyTagOverride(
      file,
      data.tags || {}
    )


  } catch (err) {
    /*
     * Un fallo leyendo tags no debe
     * interrumpir el reproductor.
     */
    console.warn(
      '[Castillo] No se pudieron ' +
      'leer los tags reales:',
      err
    )
  }
}

async function refreshStatus() {
  if (loadingStatus.value) {
    return
  }

  loadingStatus.value = true

  try {
    const result = await request('status')

    status.value =
      result.status || {}

    song.value =
      applyTagOverrideToSong(
        result.song || {}
      )

    await loadRealFileTags(
      song.value?.file
    )

    lastElapsed = number(
      result.status?.elapsed
      ?? result.song?.elapsed
      ?? 0
    )

    elapsed.value = lastElapsed
    lastSyncClock = performance.now()

    error.value = ''
  } catch (err) {
    error.value = err.message
  } finally {
    loadingStatus.value = false
  }
}

function updateLocalClock() {
  let current = lastElapsed

  if (status.value.state === 'play') {
    current += (
      performance.now() - lastSyncClock
    ) / 1000
  }

  const duration = currentDuration.value

  if (duration > 0) {
    current = Math.min(
      current,
      duration
    )
  }

  elapsed.value = Math.max(
    0,
    current
  )
}

function clearBitrateRefresh() {
  if (bitrateRefreshTimer) {
    window.clearTimeout(
      bitrateRefreshTimer
    )

    bitrateRefreshTimer = null
  }

  bitrateRefreshAttempt = 0
}


function scheduleBitrateRefresh(
  expectedFile,
  delay = 700
) {
  if (!expectedFile) {
    return
  }

  if (bitrateRefreshTimer) {
    window.clearTimeout(
      bitrateRefreshTimer
    )
  }

  bitrateRefreshTimer =
    window.setTimeout(
      async () => {
        bitrateRefreshTimer = null

        if (
          song.value?.file !== expectedFile
        ) {
          bitrateRefreshAttempt = 0
          return
        }

        try {
          const result =
            await request('status')

          if (
            result.song?.file !== expectedFile ||
            song.value?.file !== expectedFile
          ) {
            bitrateRefreshAttempt = 0
            return
          }

          const bitrate =
            number(
              result.status?.bitrate
            )

          if (bitrate > 0) {
            status.value = {
              ...status.value,
              bitrate:
                result.status.bitrate,
              audio:
                result.status.audio ??
                status.value.audio
            }

            bitrateRefreshAttempt = 0
            return
          }

          if (bitrateRefreshAttempt < 1) {
            bitrateRefreshAttempt += 1

            scheduleBitrateRefresh(
              expectedFile,
              1400
            )
          } else {
            bitrateRefreshAttempt = 0
          }

        } catch {
          bitrateRefreshAttempt = 0
        }
      },
      delay
    )
}


function applyPlayerState(data) {
  const previousFile =
    song.value?.file || ''

  const nextFile =
    data.song?.file ||
    previousFile

  const changedTrack =
    Boolean(
      nextFile &&
      previousFile &&
      nextFile !== previousFile
    )

  if (data.status) {
    const nextStatus = {
      ...data.status
    }

    const incomingBitrate =
      number(
        nextStatus.bitrate
      )

    /*
     * "0" como string es truthy en Vue.
     * Lo convertimos a vacío para no renderizar
     * falsamente "0 kbps".
     *
     * Si seguimos en la misma canción y ya
     * teníamos un bitrate válido, lo conservamos.
     */
    if (incomingBitrate <= 0) {
      if (
        !changedTrack &&
        number(
          status.value?.bitrate
        ) > 0
      ) {
        nextStatus.bitrate =
          status.value.bitrate
      } else {
        nextStatus.bitrate = ''
      }
    }

    status.value = nextStatus
  }

  if (data.song) {
    song.value =
      applyTagOverrideToSong(
        data.song
      )
  }

  lastElapsed = number(
    data.status?.elapsed
    ?? data.song?.elapsed
    ?? 0
  )

  elapsed.value = lastElapsed

  lastSyncClock = performance.now()

  if (
    song.value?.file &&
    number(
      status.value?.bitrate
    ) <= 0
  ) {
    bitrateRefreshAttempt = 0

    scheduleBitrateRefresh(
      song.value.file
    )
  } else if (
    number(
      status.value?.bitrate
    ) > 0
  ) {
    clearBitrateRefresh()
  }
}

function startEvents() {
  if (eventSource) {
    return
  }

  console.log(
    '[Castillo] Conectando eventos MPD...'
  )

  eventSource = new EventSource(
    '/castillo-api/events.php'
  )


  /*
   * Evento principal enviado por events.php
   */
  eventSource.addEventListener(
    'mpd',
    async event => {
      try {
        const data =
          JSON.parse(event.data)

        const changed =
          data.changed || []

        /*
         * Actualizar player inmediatamente.
         */
        applyPlayerState(data)

        await loadRealFileTags(
          data.song?.file ||
          song.value?.file
        )

        /*
         * Cola modificada.
         */
        if (
          changed.includes('playlist')
        ) {
          await loadQueue()
        }


        /*
         * MPD actualizó su base de datos.
         */
        if (
          changed.includes('database') ||
          changed.includes('update')
        ) {
          await loadLibrary(true)

          /*
           * Si el archivo fue modificado fuera
           * de Castillo o acaba de ser guardado,
           * volvemos a leer sus tags reales.
           */
          await loadRealFileTags(
            song.value?.file,
            true
          )
        }


        console.debug(
          '[Castillo] MPD:',
          changed
        )

      } catch (err) {
        console.error(
          '[Castillo] Evento inválido:',
          err
        )
      }
    }
  )

  /*
   * Error enviado explícitamente
   * desde PHP.
   */
  eventSource.addEventListener(
    'castillo-error',
    event => {
      try {
        const data =
          JSON.parse(event.data)

        console.error(
          '[Castillo SSE]',
          data.message
        )

        error.value =
          data.message ||
          'Error de eventos MPD'
      } catch {
        console.error(
          '[Castillo] Error SSE'
        )
      }
    }
  )

  eventSource.addEventListener(
    'open',
    () => {
      if (offlineTimer) {
        clearTimeout(
          offlineTimer
        )

        offlineTimer = null
      }

      serverOnline.value = true
      reconnecting.value = false
    }
  )

  eventSource.addEventListener(
    'error',
    () => {
      /*
       * No mostramos el bloqueo por una
       * microinterrupción de red.
       *
       * Esperamos unos segundos antes
       * de considerar la Raspberry offline.
       */
      reconnecting.value = true

      if (offlineTimer) {
        return
      }

      offlineTimer =
        window.setTimeout(
          () => {
            serverOnline.value = false
            offlineTimer = null
          },
          3500
        )
    }
  )

  /*
   * EventSource se reconecta
   * automáticamente.
   */
  eventSource.onopen = () => {
    console.log(
      '[Castillo] Eventos MPD conectados'
    )

    error.value = ''
  }


  eventSource.onerror = () => {
    console.warn(
      '[Castillo] SSE desconectado. ' +
      'Intentando reconectar...'
    )
  }
}

function stopEvents() {
  clearBitrateRefresh()

  if (!eventSource) {
    return
  }

  eventSource.close()
  eventSource = null

  console.log(
    '[Castillo] Eventos MPD cerrados'
  )
}

function startPolling() {
  /*
   * Una sola consulta inicial.
   *
   * Después todo llega por SSE.
   */
  refreshStatus()

  /*
   * MPD idle -> SSE -> Vue
   */
  startEvents()


  /*
   * Este timer NO hace llamadas HTTP.
   *
   * Solamente actualiza visualmente:
   * elapsed, barra de progreso y
   * posteriormente las letras.
   */
  if (!clockTimer) {
    clockTimer = window.setInterval(
      updateLocalClock,
      100
    )
  }
}

function stopPolling() {
  stopEvents()

  if (clockTimer) {
    clearInterval(clockTimer)
    clockTimer = null
  }
}

async function control(
  command,
  extra = {}
) {
  const result = await request(
    'control',
    {
      command,
      ...extra
    }
  )

  /*
   * La respuesta del propio comando
   * ya contiene el nuevo estado.
   *
   * No necesitamos otro GET status.
   */
  applyPlayerState(result)

  return result
}

async function togglePlayback() {
  return control('toggle')
}

async function previous() {
  await control('previous')
  await loadQueue()
}

async function next() {
  await control('next')
  await loadQueue()
}

async function seek(seconds) {
  await control(
    'seek',
    {
      value: Number(seconds)
    }
  )
}

async function setVolume(value) {
  await control(
    'volume',
    {
      value: Math.round(value)
    }
  )
}

async function setRandom(enabled) {
  await control(
    'random',
    {
      enabled
    }
  )
}

async function setRepeat(enabled) {
  await control(
    'repeat',
    {
      enabled
    }
  )
}

async function playFile(file) {
  await request(
    'play-file',
    {
      file
    }
  )

  await refreshStatus()
  await loadQueue()
}

async function playFiles(files) {
  const cleanFiles = files
    .map(item =>
      typeof item === 'string'
        ? item
        : item?.file
    )
    .filter(Boolean)

  if (!cleanFiles.length) {
    return
  }

  await request(
    'play-files',
    {
      files: cleanFiles
    }
  )

  await refreshStatus()
  await loadQueue()
}

async function playAll() {
  await request('play-all', {})

  await refreshStatus()
  await loadQueue()
}

async function queueAddMany(files) {
  const cleanFiles = files
    .map(item =>
      typeof item === 'string'
        ? item
        : item?.file
    )
    .filter(Boolean)

  if (!cleanFiles.length) {
    return
  }

  await request(
    'queue-add-many',
    {
      files: cleanFiles
    }
  )

  await loadQueue()
}

async function queueAdd(file) {
  await request(
    'queue-add',
    {
      file
    }
  )

  await loadQueue()
}

async function clearQueue() {
  await control('clear')
  await loadQueue()
}

async function playQueueItem(id) {
  await request(
    'queue-play',
    {
      id
    }
  )
}

async function removeQueueItem(id) {
  await request(
    'queue-remove',
    {
      id
    }
  )

  /*
   * SSE también lo actualizará,
   * pero esto hace la UI inmediata.
   */
  await loadQueue()
}

async function moveQueueItem(
  id,
  position
) {
  await request(
    'queue-move',
    {
      id,
      position
    }
  )

  await loadQueue()
}

async function loadQueue() {
  try {
    const result = await request('queue')

    queue.value =
      (
        result.songs || []
      ).map(item =>
        applyTagOverrideToSong(
          item
        )
      )
  } catch (err) {
    error.value = err.message
  }
}


async function loadLibrary(force = false) {
  if (
    loadingLibrary.value ||
    (
      library.value.songs.length &&
      !force
    )
  ) {
    return
  }

  loadingLibrary.value = true

  try {
    const result = await request('library')

    library.value = {
      songs: result.songs || [],
      albums: result.albums || [],
      artists: result.artists || [],
      total: result.total || 0
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loadingLibrary.value = false
  }
}


async function search(query) {
  const params = new URLSearchParams({
    action: 'search',
    q: query
  })

  const response = await fetch(
    `/castillo-api/api.php?${params.toString()}`,
    {
      cache: 'no-store'
    }
  )

  const result = await response.json()

  if (!response.ok) {
    throw new Error(
      result.error || 'Error buscando música'
    )
  }

  return result
}


async function loadHashtags(force = false) {
  if (
    loadingHashtags.value ||
    (
      hashtags.value.length &&
      !force
    )
  ) {
    return hashtags.value
  }

  loadingHashtags.value = true
  hashtagsError.value = ''

  try {
    const response =
      await fetch(
        '/castillo-api/hashtags.php',
        {
          cache: 'no-store'
        }
      )

    const result =
      await response.json()

    if (!response.ok) {
      throw new Error(
        result.error ||
        'No fue posible cargar los hashtags.'
      )
    }

    hashtags.value =
      result.hashtags || []

    hashtagStats.value =
      result.stats || {}

    return hashtags.value

  } catch (err) {
    hashtagsError.value =
      err.message ||
      'No fue posible cargar los hashtags.'

    throw err

  } finally {
    loadingHashtags.value = false
  }
}


async function loadHashtagSongs(name) {
  const hashtag =
    String(name || '')
      .trim()
      .replace(/^#+/, '')

  if (!hashtag) {
    return []
  }

  const params =
    new URLSearchParams({
      hashtag
    })

  const response =
    await fetch(
      '/castillo-api/hashtags.php?' +
      params.toString(),
      {
        cache: 'no-store'
      }
    )

  const result =
    await response.json()

  if (!response.ok) {
    throw new Error(
      result.error ||
      'No fue posible cargar las canciones del hashtag.'
    )
  }

  return (
    result.songs || []
  ).map(item =>
    applyTagOverrideToSong(
      item
    )
  )
}


async function loadSongHashtags(file) {
  if (!file) {
    return []
  }

  const params =
    new URLSearchParams({
      file
    })

  const response =
    await fetch(
      '/castillo-api/hashtags.php?' +
      params.toString(),
      {
        cache: 'no-store'
      }
    )

  const result =
    await response.json()

  if (!response.ok) {
    throw new Error(
      result.error ||
      'No fue posible cargar los hashtags de la canción.'
    )
  }

  return result.hashtags || []
}


async function loadFavorites() {
  const result = await request('favorites')

  favorites.value =
    result.files || []
}


async function toggleFavorite(file) {
  const result = await request(
    'favorite-toggle',
    {
      file
    }
  )

  await loadFavorites()

  return result.favorite
}


function isFavorite(file) {
  return favorites.value.includes(file)
}


async function loadPlaylists() {
  const result = await request('playlists')

  playlists.value =
    result.playlists || {}
}


async function createPlaylist(name) {
  await request(
    'playlist-create',
    {
      name
    }
  )

  await loadPlaylists()
}


async function deletePlaylist(name) {
  await request(
    'playlist-delete',
    {
      name
    }
  )

  await loadPlaylists()
}


async function addToPlaylist(name, file) {
  await request(
    'playlist-add',
    {
      name,
      file
    }
  )

  await loadPlaylists()
}


async function removeFromPlaylist(
  name,
  file
) {
  await request(
    'playlist-remove',
    {
      name,
      file
    }
  )

  await loadPlaylists()
}


async function playPlaylist(name) {
  await request(
    'playlist-play',
    {
      name
    }
  )

  await refreshStatus()
  await loadQueue()
}


function coverUrl(file) {
  if (!file) {
    return ''
  }

  return (
    '/coverart.php/' +
    encodeURIComponent(file)
  )
}


const currentDuration = computed(() => {
  return number(
    status.value.duration
    ?? song.value.duration
    ?? song.value.time
    ?? 0
  )
})


const playing = computed(() => {
  return status.value.state === 'play'
})


const paused = computed(() => {
  return status.value.state === 'pause'
})


const volume = computed(() => {
  return number(status.value.volume)
})


const random = computed(() => {
  return status.value.random === '1'
})


const repeat = computed(() => {
  return status.value.repeat === '1'
})


export function useCastilloApi() {
  return {
    status,
    song,
    queue,
    library,
    favorites,
    playlists,

    hashtags,
    hashtagStats,
    loadingHashtags,
    hashtagsError,

    elapsed,

    loadingStatus,
    loadingLibrary,

    error,

    currentDuration,
    playing,
    paused,
    volume,
    random,
    repeat,

    startPolling,
    stopPolling,

    refreshStatus,
    loadQueue,
    loadLibrary,
    loadFavorites,
    loadPlaylists,
    loadHashtags,
    loadHashtagSongs,
    loadSongHashtags,

    search,

    togglePlayback,
    previous,
    next,
    seek,
    setVolume,
    setRandom,
    setRepeat,

    playFile,
    playFiles,
    playAll,
    queueAddMany,
    queueAdd,
    clearQueue,
    playQueueItem,
    removeQueueItem,
    moveQueueItem,

    toggleFavorite,
    isFavorite,

    createPlaylist,
    deletePlaylist,
    addToPlaylist,
    removeFromPlaylist,
    playPlaylist,

    applyTagOverride,
    loadRealFileTags,

    coverUrl,

    serverOnline,
    reconnecting
  }
}