import {
  computed,
  ref
} from 'vue'

const route = ref({
  view: 'home',
  artist: '',
  album: '',
  albumArtist: '',
  playlist: '',
  hashtag: '',
  file: ''
})

let initialized = false


function decode(value = '') {
  try {
    return decodeURIComponent(value)
  } catch {
    return value
  }
}


function encode(value = '') {
  return encodeURIComponent(value)
}


function parseHash() {
  const raw = window.location.hash
    .replace(/^#\/?/, '')

  const parts = raw
    .split('/')
    .filter(Boolean)
    .map(decode)

  const view = parts[0] || 'home'

  const result = {
    view,
    artist: '',
    album: '',
    albumArtist: '',
    playlist: '',
    hashtag: '',
    file: ''
  }


  if (
    view === 'artists' &&
    parts[1]
  ) {
    result.artist = parts[1]
  }


  if (
    view === 'albums' &&
    parts[1]
  ) {
    result.albumArtist =
      parts[1]

    result.album =
      parts[2] || ''
  }


  if (
    view === 'playlists' &&
    parts[1]
  ) {
    result.playlist =
      parts[1]
  }

  if (
    view === 'hashtags' &&
    parts[1]
  ) {
    result.hashtag =
      parts[1]
  }

  if (
    view === 'tags-edit' &&
    parts[1]
  ) {
    result.file =
      parts[1]
  }


  return result
}


function routeToHash(target) {
  switch (target.view) {

    case 'artists':
      if (target.artist) {
        return (
          '#/artists/' +
          encode(target.artist)
        )
      }

      return '#/artists'


    case 'albums':
      if (target.album) {
        return (
          '#/albums/' +
          encode(
            target.albumArtist || '—'
          ) +
          '/' +
          encode(target.album)
        )
      }

      return '#/albums'


    case 'playlists':
      if (target.playlist) {
        return (
          '#/playlists/' +
          encode(target.playlist)
        )
      }

      return '#/playlists'

    case 'hashtags':
      if (target.hashtag) {
        return (
          '#/hashtags/' +
          encode(target.hashtag)
        )
      }

      return '#/hashtags'


    case 'tags-edit':
      if (target.file) {
        return (
          '#/tags-edit/' +
          encode(target.file)
        )
      }

      return '#/tags-edit'


    default:
      return (
        '#/' +
        encode(
          target.view || 'home'
        )
      )
  }
}


function applyCurrentLocation() {
  route.value =
    parseHash()

  scrollToTop()
}


function scrollToTop() {
  requestAnimationFrame(() => {
    const container =
      document.getElementById(
        'castillo-main-scroll'
      )

    container?.scrollTo({
      top: 0,
      left: 0,
      behavior: 'auto'
    })
  })
}


function navigate(
  view,
  options = {}
) {
  const target = {
    view,
    artist:
      options.artist || '',
    album:
      options.album || '',
    albumArtist:
      options.albumArtist || '',
    playlist:
      options.playlist || '',
    hashtag:
      options.hashtag || '',
    file:
      options.file || ''
  }

  const hash =
    routeToHash(target)

  /*
   * pushState crea una entrada real
   * en el historial de Chrome.
   */
  window.history.pushState(
    target,
    '',
    hash
  )

  route.value = target

  scrollToTop()
}


function replace(
  view,
  options = {}
) {
  const target = {
    view,
    artist:
      options.artist || '',
    album:
      options.album || '',
    albumArtist:
      options.albumArtist || '',
    playlist:
      options.playlist || '',
    hashtag:
      options.hashtag || '',
    file:
      options.file || ''
  }

  window.history.replaceState(
    target,
    '',
    routeToHash(target)
  )

  route.value = target
}


function goBack() {
  window.history.back()
}


function startNavigation() {
  if (initialized) {
    return
  }

  initialized = true

  /*
   * Primera carga.
   */
  if (!window.location.hash) {
    replace('home')
  } else {
    applyCurrentLocation()
  }


  /*
   * Botones Atrás / Adelante
   * del navegador.
   */
  window.addEventListener(
    'popstate',
    applyCurrentLocation
  )


  /*
   * También permite pegar directamente
   * una URL con #/artists/... etc.
   */
  window.addEventListener(
    'hashchange',
    applyCurrentLocation
  )
}


function stopNavigation() {
  if (!initialized) {
    return
  }

  window.removeEventListener(
    'popstate',
    applyCurrentLocation
  )

  window.removeEventListener(
    'hashchange',
    applyCurrentLocation
  )

  initialized = false
}


const currentView = computed(
  () => route.value.view
)


export function useCastilloNavigation() {
  return {
    route,
    currentView,

    navigate,
    replace,
    goBack,

    startNavigation,
    stopNavigation,
    scrollToTop
  }
}