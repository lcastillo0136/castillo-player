<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted
} from 'vue'

import {
  useVuMeter
} from '../composables/useVuMeter'


const {
  running,
  waitingForGesture,
  error,

  leftRmsDb,
  rightRmsDb,

  leftLevel,
  rightLevel,

  leftPeakLevel,
  rightPeakLevel,

  start,
  stop
} = useVuMeter()


const leftWidth =
  computed(
    () =>
      `${leftLevel.value * 100}%`
  )

const rightWidth =
  computed(
    () =>
      `${rightLevel.value * 100}%`
  )

const leftPeakPosition =
  computed(
    () =>
      `${leftPeakLevel.value * 100}%`
  )

const rightPeakPosition =
  computed(
    () =>
      `${rightPeakLevel.value * 100}%`
  )


function formatDb(value) {
  if (
    !Number.isFinite(value)
  ) {
    return '-60.0'
  }

  return value.toFixed(1)
}


onMounted(
  () => {
    start()
  }
)


onBeforeUnmount(
  () => {
    stop()
  }
)
</script>


<template>
  <section
    class="castillo-vu"
  >
    <header
      class="castillo-vu__header"
    >
      <div>
        <div
          class="castillo-vu__eyebrow"
        >
          VISUALIZADOR
        </div>

        <div
          class="castillo-vu__title"
        >
          Nivel estéreo
        </div>
      </div>

      <div
        class="castillo-vu__status"
        :class="{
          'castillo-vu__status--active':
            running
        }"
      >
        <span
          class="castillo-vu__status-dot"
        />

        {{
          running
            ? 'Activo'
            : 'En espera'
        }}
      </div>
    </header>


    <div
      v-if="waitingForGesture"
      class="castillo-vu__notice"
    >
      Toca o haz clic en Castillo para activar el visualizador
    </div>


    <div
      v-if="error"
      class="castillo-vu__error"
    >
      {{ error }}
    </div>


    <div
      class="castillo-vu__scale"
    >
      <span>-60</span>
      <span>-48</span>
      <span>-36</span>
      <span>-24</span>
      <span>-12</span>
      <span>-6</span>
      <span>0</span>
    </div>


    <div
      class="castillo-vu__channels"
    >
      <div
        class="castillo-vu__channel"
      >
        <div
          class="castillo-vu__channel-label"
        >
          <span>L</span>

          <strong>
            {{ formatDb(leftRmsDb) }}
            <small>dB</small>
          </strong>
        </div>


        <div
          class="castillo-vu__track"
        >
          <div
            class="castillo-vu__grid"
          />

          <div
            class="castillo-vu__fill"
            :style="{
              width: leftWidth
            }"
          />

          <div
            class="castillo-vu__peak"
            :style="{
              left: leftPeakPosition
            }"
          />
        </div>
      </div>


      <div
        class="castillo-vu__channel"
      >
        <div
          class="castillo-vu__channel-label"
        >
          <span>R</span>

          <strong>
            {{ formatDb(rightRmsDb) }}
            <small>dB</small>
          </strong>
        </div>


        <div
          class="castillo-vu__track"
        >
          <div
            class="castillo-vu__grid"
          />

          <div
            class="castillo-vu__fill"
            :style="{
              width: rightWidth
            }"
          />

          <div
            class="castillo-vu__peak"
            :style="{
              left: rightPeakPosition
            }"
          />
        </div>
      </div>
    </div>


    <footer
      class="castillo-vu__footer"
    >
      RMS
      <span>•</span>
      PEAK
      <span>•</span>
      -60 / 0 dBFS
    </footer>
  </section>
</template>


<style scoped>
.castillo-vu {
  width: 100%;
  padding: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 18px;
  background:
    linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.035),
      rgba(255, 255, 255, 0.012)
    );
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.04);
}


.castillo-vu__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}


.castillo-vu__eyebrow {
  margin-bottom: 2px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.18em;
  opacity: 0.42;
}


.castillo-vu__title {
  font-size: 15px;
  font-weight: 650;
}


.castillo-vu__status {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  opacity: 0.5;
}


.castillo-vu__status-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: currentColor;
}


.castillo-vu__status--active {
  opacity: 0.9;
}


.castillo-vu__notice {
  margin-bottom: 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.045);
  font-size: 12px;
  opacity: 0.75;
}


.castillo-vu__error {
  margin-bottom: 16px;
  font-size: 12px;
  opacity: 0.75;
}


.castillo-vu__scale {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  margin: 0 0 6px 48px;
  font-size: 9px;
  opacity: 0.32;
}


.castillo-vu__scale span {
  text-align: center;
}


.castillo-vu__scale span:first-child {
  text-align: left;
}


.castillo-vu__scale span:last-child {
  text-align: right;
}


.castillo-vu__channels {
  display: flex;
  flex-direction: column;
  gap: 14px;
}


.castillo-vu__channel {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  align-items: center;
  gap: 12px;
}


.castillo-vu__channel-label {
  display: flex;
  flex-direction: column;
  justify-content: center;
}


.castillo-vu__channel-label > span {
  font-size: 14px;
  font-weight: 800;
}


.castillo-vu__channel-label strong {
  margin-top: 1px;
  white-space: nowrap;
  font-size: 9px;
  font-weight: 500;
  opacity: 0.4;
}


.castillo-vu__channel-label small {
  font-size: inherit;
  font-weight: inherit;
}


.castillo-vu__track {
  position: relative;
  height: 22px;
  overflow: hidden;
  border-radius: 6px;
  background:
    rgba(255, 255, 255, 0.035);
}


.castillo-vu__grid {
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;

  background:
    repeating-linear-gradient(
      90deg,
      transparent 0,
      transparent calc(4% - 2px),
      rgba(10, 10, 10, 0.78)
        calc(4% - 2px),
      rgba(10, 10, 10, 0.78)
        4%
    );
}


.castillo-vu__fill {
  position: absolute;
  inset: 0 auto 0 0;
  z-index: 1;
  min-width: 0;
  border-radius: inherit;

  background:
    linear-gradient(
      90deg,
      rgba(232, 104, 77, 0.65),
      rgba(242, 116, 88, 0.95)
    );

  transition:
    width 45ms linear;
}


.castillo-vu__peak {
  position: absolute;
  top: 2px;
  bottom: 2px;
  z-index: 4;

  width: 2px;

  transform:
    translateX(-1px);

  border-radius: 2px;

  background:
    rgba(255, 255, 255, 0.9);

  box-shadow:
    0 0 7px
    rgba(255, 255, 255, 0.35);

  transition:
    left 45ms linear;
}


.castillo-vu__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  margin-top: 14px;
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.08em;
  opacity: 0.28;
}


@media (
  max-width: 640px
) {
  .castillo-vu {
    padding: 14px;
    border-radius: 15px;
  }


  .castillo-vu__header {
    margin-bottom: 18px;
  }


  .castillo-vu__scale {
    margin-left: 42px;
  }


  .castillo-vu__channel {
    grid-template-columns:
      30px
      minmax(0, 1fr);

    gap: 10px;
  }


  .castillo-vu__track {
    height: 20px;
  }


  .castillo-vu__footer {
    display: none;
  }
}
</style>