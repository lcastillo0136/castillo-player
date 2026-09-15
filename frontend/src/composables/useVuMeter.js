import {
  computed,
  ref
} from 'vue'


const running =
  ref(false)

const waitingForGesture =
  ref(false)

const error =
  ref('')


const leftRmsDb =
  ref(-60)

const rightRmsDb =
  ref(-60)

const leftPeakDb =
  ref(-60)

const rightPeakDb =
  ref(-60)


let desired =
  false

let audio =
  null

let context =
  null

let analyserL =
  null

let analyserR =
  null

let dataL =
  null

let dataR =
  null

let animationFrame =
  0


let smoothRmsL =
  0

let smoothRmsR =
  0

let smoothPeakL =
  0

let smoothPeakR =
  0


let gestureArmed =
  false


function streamUrl() {
  return (
    `http://${window.location.hostname}:8000/` +
    `?castillo-vu=${Date.now()}`
  )
}


function linearToDb(value) {
  return (
    20 *
    Math.log10(
      Math.max(
        value,
        0.000001
      )
    )
  )
}


function clampDb(value) {
  return Math.max(
    -60,
    Math.min(
      0,
      value
    )
  )
}


function smooth(
  current,
  target,
  attack = 0.35,
  release = 0.08
) {
  const factor =
    target > current
      ? attack
      : release

  return (
    current +
    (
      target -
      current
    ) *
    factor
  )
}


function measure(
  analyser,
  data
) {
  analyser.getFloatTimeDomainData(
    data
  )

  let sum =
    0

  let peak =
    0


  for (
    let i = 0;
    i < data.length;
    i += 1
  ) {
    const sample =
      Math.abs(
        data[i]
      )

    sum +=
      sample *
      sample

    if (
      sample > peak
    ) {
      peak =
        sample
    }
  }


  return {
    rms:
      Math.sqrt(
        sum /
        data.length
      ),

    peak
  }
}


function updateLevels() {
  if (
    !running.value ||
    !analyserL ||
    !analyserR
  ) {
    return
  }


  const left =
    measure(
      analyserL,
      dataL
    )

  const right =
    measure(
      analyserR,
      dataR
    )


  smoothRmsL =
    smooth(
      smoothRmsL,
      left.rms
    )

  smoothRmsR =
    smooth(
      smoothRmsR,
      right.rms
    )


  smoothPeakL =
    smooth(
      smoothPeakL,
      left.peak,
      0.65,
      0.12
    )

  smoothPeakR =
    smooth(
      smoothPeakR,
      right.peak,
      0.65,
      0.12
    )


  leftRmsDb.value =
    clampDb(
      linearToDb(
        smoothRmsL
      )
    )

  rightRmsDb.value =
    clampDb(
      linearToDb(
        smoothRmsR
      )
    )

  leftPeakDb.value =
    clampDb(
      linearToDb(
        smoothPeakL
      )
    )

  rightPeakDb.value =
    clampDb(
      linearToDb(
        smoothPeakR
      )
    )


  animationFrame =
    requestAnimationFrame(
      updateLevels
    )
}


async function setAnalysisStream(
  enabled
) {
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
          JSON.stringify({
            type:
              'analysis',

            enabled
          })
      }
    )


  const data =
    await response.json()


  if (
    !response.ok ||
    data?.error
  ) {
    throw new Error(
      data?.error ||
      'No fue posible controlar el stream de análisis.'
    )
  }


  return data
}


function cleanupGraph() {
  if (
    animationFrame
  ) {
    cancelAnimationFrame(
      animationFrame
    )

    animationFrame =
      0
  }


  running.value =
    false


  if (
    audio
  ) {
    audio.pause()

    audio.removeAttribute(
      'src'
    )

    audio.load()

    audio =
      null
  }


  if (
    context &&
    context.state !==
      'closed'
  ) {
    context.close()
      .catch(
        () => {}
      )
  }


  context =
    null

  analyserL =
    null

  analyserR =
    null

  dataL =
    null

  dataR =
    null


  smoothRmsL =
    0

  smoothRmsR =
    0

  smoothPeakL =
    0

  smoothPeakR =
    0
}


function clearGesture() {
  if (
    !gestureArmed
  ) {
    return
  }


  window.removeEventListener(
    'pointerdown',
    resumeFromGesture,
    true
  )

  window.removeEventListener(
    'keydown',
    resumeFromGesture,
    true
  )


  gestureArmed =
    false
}


function armGesture() {
  if (
    gestureArmed
  ) {
    return
  }


  gestureArmed =
    true

  waitingForGesture.value =
    true


  window.addEventListener(
    'pointerdown',
    resumeFromGesture,
    {
      capture: true
    }
  )

  window.addEventListener(
    'keydown',
    resumeFromGesture,
    {
      capture: true
    }
  )
}


async function createGraph() {
  cleanupGraph()


  audio =
    new Audio()

  audio.crossOrigin =
    'anonymous'

  audio.preload =
    'none'

  audio.src =
    streamUrl()


  context =
    new AudioContext()


  const source =
    context.createMediaElementSource(
      audio
    )


  const splitter =
    context.createChannelSplitter(
      2
    )


  analyserL =
    context.createAnalyser()

  analyserR =
    context.createAnalyser()


  analyserL.fftSize =
    2048

  analyserR.fftSize =
    2048


  const silent =
    context.createGain()

  silent.gain.value =
    0


  source.connect(
    splitter
  )


  splitter.connect(
    analyserL,
    0
  )

  splitter.connect(
    analyserR,
    1
  )


  analyserL.connect(
    silent
  )

  analyserR.connect(
    silent
  )


  silent.connect(
    context.destination
  )


  dataL =
    new Float32Array(
      analyserL.fftSize
    )

  dataR =
    new Float32Array(
      analyserR.fftSize
    )


  await context.resume()


  if (
    context.state !==
    'running'
  ) {
    const err =
      new Error(
        'AudioContext bloqueado'
      )

    err.name =
      'NotAllowedError'

    throw err
  }


  await audio.play()


  running.value =
    true

  waitingForGesture.value =
    false

  error.value =
    ''


  updateLevels()
}


async function resumeFromGesture() {
  clearGesture()


  if (
    !desired
  ) {
    return
  }


  try {
    await createGraph()

  } catch (err) {
    cleanupGraph()

    error.value =
      err?.message ||
      'No fue posible iniciar el VU Meter.'

    armGesture()
  }
}


async function start() {
  if (
    desired &&
    running.value
  ) {
    return
  }


  desired =
    true

  error.value =
    ''


  try {
    await setAnalysisStream(
      true
    )

    await createGraph()

  } catch (err) {
    cleanupGraph()


    if (
      !desired
    ) {
      return
    }


    if (
      err?.name !==
      'NotAllowedError'
    ) {
      console.warn(
        '[Castillo VU]',
        err
      )
    }


    armGesture()
  }
}


async function stop() {
  desired =
    false

  clearGesture()

  waitingForGesture.value =
    false

  cleanupGraph()


  try {
    await setAnalysisStream(
      false
    )

  } catch (err) {
    console.warn(
      '[Castillo VU]',
      err
    )
  }
}


function dbToLevel(
  db
) {
  return Math.max(
    0,
    Math.min(
      1,
      (
        db +
        60
      ) /
      60
    )
  )
}


const leftLevel =
  computed(
    () =>
      dbToLevel(
        leftRmsDb.value
      )
  )

const rightLevel =
  computed(
    () =>
      dbToLevel(
        rightRmsDb.value
      )
  )

const leftPeakLevel =
  computed(
    () =>
      dbToLevel(
        leftPeakDb.value
      )
  )

const rightPeakLevel =
  computed(
    () =>
      dbToLevel(
        rightPeakDb.value
      )
  )


export function useVuMeter() {
  return {
    running,
    waitingForGesture,
    error,

    leftRmsDb,
    rightRmsDb,

    leftPeakDb,
    rightPeakDb,

    leftLevel,
    rightLevel,

    leftPeakLevel,
    rightPeakLevel,

    start,
    stop
  }
}