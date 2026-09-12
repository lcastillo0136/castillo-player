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
    return
  }


  changing.value = true
  error.value = ''


  try {
    const body =
      output.type === 'local'
        ? {
            type: 'local'
          }
        : {
            type: 'bluetooth',
            mac: output.mac
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