import {
  computed,
  ref
} from 'vue'


const outputs = ref([])
const selected = ref('local')

const loading = ref(false)
const changing = ref(false)

const error = ref('')

let loaded = false

let browserAudio = null
let browserResumeOutput = null
let browserResumeArmed = false

function browserStreamUrl(
  port = 8000
) {
  return (
    `${window.location.protocol}//` +
    `${window.location.hostname}:` +
    `${port}/`
  )
}

function clearBrowserResume() {
  if (!browserResumeArmed) {
    return
  }

  window.removeEventListener(
    'pointerdown',
    resumeBrowserFromGesture,
    true
  )

  window.removeEventListener(
    'keydown',
    resumeBrowserFromGesture,
    true
  )

  browserResumeArmed = false
  browserResumeOutput = null
}

async function resumeBrowserFromGesture() {
  const output =
    browserResumeOutput

  clearBrowserResume()

  if (!output) {
    return
  }

  try {
    await startBrowserAudio(
      output,
      true
    )

  } catch (err) {
    console.error(
      '[Castillo browser audio resume]',
      err
    )

    /*
     * Si falló por una condición transitoria,
     * permitimos que el siguiente gesto
     * vuelva a intentarlo.
     */
    armBrowserResume(
      output
    )
  }
}

function armBrowserResume(
  output
) {
  browserResumeOutput =
    output

  if (browserResumeArmed) {
    return
  }

  browserResumeArmed = true

  window.addEventListener(
    'pointerdown',
    resumeBrowserFromGesture,
    {
      once: true,
      capture: true
    }
  )

  window.addEventListener(
    'keydown',
    resumeBrowserFromGesture,
    {
      once: true,
      capture: true
    }
  )
}

function stopBrowserAudio() {
  clearBrowserResume()

  if (!browserAudio) {
    return
  }

  browserAudio.pause()

  browserAudio.removeAttribute(
    'src'
  )

  browserAudio.load()

  browserAudio = null
}

function wait(
  milliseconds
) {
  return new Promise(
    resolve =>
      window.setTimeout(
        resolve,
        milliseconds
      )
  )
}

async function startBrowserAudio(
  output,
  immediate = false
) {
  clearBrowserResume()

  const port =
    Number(
      output?.port
      ?? 8000
    )


  const baseUrl =
    browserStreamUrl(
      port
    )


  /*
   * MPD puede tardar unos instantes
   * en levantar su servidor HTTP.
   *
   * Un elemento <audio> que recibe
   * ERR_EMPTY_RESPONSE puede quedar
   * permanentemente en estado de error,
   * por lo que cada intento debe usar
   * un Audio nuevo.
   */
  stopBrowserAudio()


  const delays =
    immediate
      ? [
          0,
          250,
          500,
          750
        ]
      : [
          250,
          500,
          750,
          1000
        ]


  let lastError = null


  for (
    let attempt = 0;
    attempt < delays.length;
    attempt += 1
  ) {
    const delay =
      delays[attempt]


    if (delay > 0) {
      await wait(
        delay
      )
    }


    const audio =
      new Audio()


    audio.preload =
      'none'


    /*
     * Evita reutilizar una respuesta
     * fallida del intento anterior.
     */
    audio.src =
      `${baseUrl}?castillo=${Date.now()}`


    browserAudio =
      audio


    try {
      await audio.play()

      console.log(
        '[Castillo browser audio] conectado',
        baseUrl
      )

      return

    } catch (err) {
      lastError =
        err

      console.warn(
        '[Castillo browser audio] intento',
        attempt + 1,
        err
      )


      audio.pause()

      audio.removeAttribute(
        'src'
      )

      audio.load()


      if (
        browserAudio === audio
      ) {
        browserAudio = null
      }
    }
  }


  console.error(
    '[Castillo browser audio]',
    lastError
  )


  throw new Error(
    'No fue posible conectar con el stream de audio del dispositivo.'
  )
}

const currentOutput = computed(() => {
  return (
    outputs.value.find(
      output =>
        output.id ===
        selected.value
    ) ||
    outputs.value[0] ||
    null
  )
})

async function loadAudioOutputs(
  force = false
) {
  if (
    loaded &&
    !force
  ) {
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response =
      await fetch(
        '/castillo-api/audio-output.php',
        {
          cache: 'no-store'
        }
      )

    const data =
      await response.json()

    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible cargar las salidas de audio.'
      )
    }

    outputs.value =
      Array.isArray(
        data.outputs
      )
        ? data.outputs
        : []

    selected.value =
      data.selected ||
      'local'

    if (
      selected.value ===
      'browser'
    ) {
      const browserOutput =
        outputs.value.find(
          output =>
            output.type ===
            'browser'
        )


      if (browserOutput) {
        try {
          /*
           * Tras una recarga el servidor HTTP de MPD
           * ya está activo, así que intentamos
           * reconectar inmediatamente.
           */
          await startBrowserAudio(
            browserOutput,
            true
          )

        } catch (err) {
          console.warn(
            '[Castillo browser audio] ' +
            'autoplay bloqueado; esperando interacción',
            err
          )

          /*
           * Fallback para navegadores que bloquean
           * reproducción con sonido tras F5.
           */
          armBrowserResume(
            browserOutput
          )
        }
      }

    } else {
      stopBrowserAudio()
    }

    if (
      selected.value !==
      'browser'
    ) {
      stopBrowserAudio()
    }

    loaded = true

  } catch (err) {
    error.value =
      err.message ||
      'Error cargando las salidas.'

    console.error(
      '[Castillo audio output]',
      err
    )

  } finally {
    loading.value = false
  }
}

async function selectAudioOutput(
  output
) {
  if (
    !output ||
    changing.value
  ) {
    return
  }


  if (
    output.id ===
    selected.value
  ) {
    if (
      output.type ===
      'browser'
    ) {
      await startBrowserAudio(
        output
      )
    }

    return
  }


  changing.value = true
  error.value = ''


  try {
    let body

    if (
      output.type === 'local'
    ) {
      body = {
        type: 'local'
      }

    } else if (
      output.type === 'browser'
    ) {
      body = {
        type: 'browser'
      }

    } else {
      body = {
        type: 'bluetooth',
        mac: output.mac
      }
    }

    const response =
      await fetch(
        '/castillo-api/audio-output.php',
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify(
              body
            )
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible cambiar la salida.'
      )
    }


    outputs.value =
      Array.isArray(
        data.outputs
      )
        ? data.outputs
        : outputs.value


    selected.value =
      data.selected ||
      output.id

    if (
      output.type === 'browser'
    ) {
      await startBrowserAudio(
        output
      )

    } else {
      stopBrowserAudio()
    }


  } catch (err) {
    error.value =
      err.message ||
      'No fue posible cambiar la salida.'

    console.error(
      '[Castillo audio output]',
      err
    )

    throw err

  } finally {
    changing.value = false
  }
}


export function useAudioOutput() {
  return {
    outputs,
    selected,

    currentOutput,

    loading,
    changing,
    error,

    loadAudioOutputs,
    selectAudioOutput
  }
}