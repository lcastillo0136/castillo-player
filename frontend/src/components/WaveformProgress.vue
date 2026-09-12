<script setup>
import {
  computed,
  ref,
  watch
} from 'vue'

import {
  getWaveform
} from '../composables/useWaveform'


const props = defineProps({
  file: {
    type: String,
    default: ''
  },

  current: {
    type: Number,
    default: 0
  },

  duration: {
    type: Number,
    default: 0
  },

  /*
   * Resolución real obtenida
   * del backend.
   */
  bars: {
    type: Number,
    default: 220
  },

  /*
   * Número de barras dibujadas.
   */
  visualBars: {
    type: Number,
    default: 56
  },

  height: {
    type: Number,
    default: 40
  },

  interactive: {
    type: Boolean,
    default: true
  },

  activeColor: {
    type: String,
    default: '#111827'
  },

  inactiveColor: {
    type: String,
    default: '#dde1e7'
  }
})


const emit = defineEmits([
  'seek'
])


const waveform = ref([])
const container = ref(null)

const dragging = ref(false)
const dragPercent = ref(null)


/*
 * Geometría visual.
 *
 * Ya NO usamos un viewBox de 100 px.
 * Cada barra dispone realmente de:
 *
 *   barra + separación
 */
const BAR_WIDTH = 2.2
const BAR_GAP = 2.6

const TOP_BOTTOM_SPACE = 2


const realProgress = computed(() => {
  if (props.duration <= 0) {
    return 0
  }

  return Math.min(
    100,
    Math.max(
      0,
      props.current /
      props.duration *
      100
    )
  )
})


const visualProgress = computed(() => {
  if (dragPercent.value !== null) {
    return dragPercent.value
  }

  return realProgress.value
})


async function load() {
  waveform.value = []

  if (!props.file) {
    return
  }

  try {
    waveform.value =
      await getWaveform(
        props.file,
        props.bars
      )
  } catch (error) {
    console.error(
      '[Castillo waveform]',
      error
    )
  }
}


watch(
  () => [
    props.file,
    props.bars
  ],
  load,
  {
    immediate: true
  }
)


function clamp(
  value,
  min,
  max
) {
  return Math.min(
    max,
    Math.max(
      min,
      value
    )
  )
}


/*
 * Reduce las 220 muestras reales
 * a unas 50-60 barras visuales.
 *
 * Conservamos especialmente los picos.
 */
const groupedWaveform = computed(() => {
  const source =
    waveform.value

  if (
    !Array.isArray(source) ||
    !source.length
  ) {
    return []
  }


  const count =
    Math.max(
      20,
      Math.min(
        props.visualBars,
        source.length
      )
    )


  const result = []


  for (
    let index = 0;
    index < count;
    index += 1
  ) {
    const start =
      Math.floor(
        index *
        source.length /
        count
      )

    const end =
      Math.max(
        start + 1,
        Math.floor(
          (index + 1) *
          source.length /
          count
        )
      )


    const chunk =
      source.slice(
        start,
        end
      )


    const peak =
      Math.max(
        ...chunk
      )


    const average =
      chunk.reduce(
        (sum, value) =>
          sum +
          Number(value || 0),
        0
      ) /
      chunk.length


    /*
     * Damos bastante más importancia
     * al pico que al promedio.
     *
     * Esto evita el aspecto plano.
     */
    result.push(
      peak * 0.78 +
      average * 0.22
    )
  }


  return result
})


/*
 * Normalización VISUAL.
 *
 * Una canción masterizada puede tener
 * niveles muy similares durante casi
 * todo el tema.
 *
 * Si simplemente dibujamos 0.72,
 * 0.76, 0.74, 0.79...
 * todas las barras parecen iguales.
 *
 * Aquí expandimos ese rango visualmente
 * manteniendo la forma relativa.
 */
const displayWaveform = computed(() => {
  const source =
    groupedWaveform.value

  if (!source.length) {
    return []
  }


  const sorted =
    [...source].sort(
      (a, b) => a - b
    )


  const lowIndex =
    Math.floor(
      sorted.length * 0.08
    )

  const highIndex =
    Math.min(
      sorted.length - 1,
      Math.floor(
        sorted.length * 0.94
      )
    )


  const low =
    sorted[lowIndex]

  const high =
    sorted[highIndex]


  const range =
    Math.max(
      0.08,
      high - low
    )


  const normalized =
    source.map(value => {
      const relative =
        clamp(
          (value - low) /
          range,
          0,
          1
        )


      /*
       * Altura mínima de 16%.
       *
       * Los silencios siguen siendo
       * visibles, como en la referencia.
       */
      return (
        0.16 +
        Math.pow(
          relative,
          0.92
        ) * 0.84
      )
    })


  /*
   * Suavizado ligero.
   *
   * Antes estábamos suavizando demasiado,
   * lo que convertía todo en una franja.
   */
  return normalized.map(
    (
      value,
      index,
      values
    ) => {
      const previous =
        values[index - 1] ??
        value

      const next =
        values[index + 1] ??
        value


      return (
        previous * 0.10 +
        value * 0.80 +
        next * 0.10
      )
    }
  )
})


const svgWidth = computed(() => {
  if (!displayWaveform.value.length) {
    return 100
  }

  return (
    displayWaveform.value.length *
      BAR_WIDTH +

    (
      displayWaveform.value.length -
      1
    ) *
      BAR_GAP
  )
})


const barGeometry = computed(() => {
  /*
   * Nuestro viewBox siempre mide
   * 40 unidades verticales.
   */
  const CENTER = 20

  const MAX_HALF_HEIGHT =
    18 - TOP_BOTTOM_SPACE

  const MIN_HALF_HEIGHT = 2.4


  return displayWaveform.value.map(
    value => {
      const halfHeight =
        MIN_HALF_HEIGHT +
        value *
        (
          MAX_HALF_HEIGHT -
          MIN_HALF_HEIGHT
        )


      return {
        y:
          CENTER -
          halfHeight,

        height:
          halfHeight * 2
      }
    }
  )
})


function pointerPercent(event) {
  const element =
    container.value

  if (!element) {
    return 0
  }


  const rect =
    element.getBoundingClientRect()


  const x =
    event.clientX -
    rect.left


  return Math.min(
    100,
    Math.max(
      0,
      x /
      rect.width *
      100
    )
  )
}


function pointerDown(event) {
  if (
    !props.interactive ||
    props.duration <= 0
  ) {
    return
  }


  dragging.value = true


  event.currentTarget
    .setPointerCapture?.(
      event.pointerId
    )


  dragPercent.value =
    pointerPercent(event)
}


function pointerMove(event) {
  if (!dragging.value) {
    return
  }


  dragPercent.value =
    pointerPercent(event)
}


function pointerUp(event) {
  if (!dragging.value) {
    return
  }


  const percent =
    pointerPercent(event)


  dragging.value = false

  dragPercent.value =
    percent


  emit(
    'seek',
    props.duration *
    percent /
    100
  )


  window.setTimeout(
    () => {
      dragPercent.value = null
    },
    150
  )
}


function pointerCancel() {
  dragging.value = false
  dragPercent.value = null
}
</script>


<template>
  <div
    ref="container"
    class="relative
           w-full
           select-none"
    :class="
      interactive &&
      duration > 0
        ? 'cursor-pointer'
        : ''
    "
    :style="{
      height: `${height}px`,
      touchAction:
        interactive
          ? 'none'
          : 'auto'
    }"
    @pointerdown="pointerDown"
    @pointermove="pointerMove"
    @pointerup="pointerUp"
    @pointercancel="pointerCancel"
  >
    <!-- REAL WAVEFORM -->
    <svg
      v-if="displayWaveform.length"
      class="block
             h-full
             w-full
             overflow-visible"
      :viewBox="
        `0 0 ${svgWidth} 40`
      "
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <rect
        v-for="
          (bar, index)
          in barGeometry
        "
        :key="index"

        :x="
          index *
          (
            BAR_WIDTH +
            BAR_GAP
          )
        "

        :y="bar.y"

        :width="BAR_WIDTH"

        :height="bar.height"

        :rx="
          BAR_WIDTH / 2
        "

        :fill="
          (
            (
              index + 0.5
            ) /
            displayWaveform.length *
            100
          ) <= visualProgress
            ? activeColor
            : inactiveColor
        "
      />
    </svg>


    <!-- LOADING / EMPTY -->
    <div
      v-else
      class="absolute
             inset-0
             flex
             items-center"
    >
      <div
        class="h-[2px]
               w-full
               rounded-full"
        :style="{
          backgroundColor:
            inactiveColor
        }"
      />
    </div>
  </div>
</template>