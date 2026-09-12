<script setup>
import {
  RadioTower,
  RefreshCw,
  WifiOff
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'


const {
  serverOnline,
  reconnecting
} = useCastilloApi()
</script>


<template>
  <Transition
    enter-active-class="
      transition-opacity duration-300
    "
    enter-from-class="
      opacity-0
    "
    enter-to-class="
      opacity-100
    "
    leave-active-class="
      transition-opacity duration-300
    "
    leave-from-class="
      opacity-100
    "
    leave-to-class="
      opacity-0
    "
  >
    <div
      v-if="!serverOnline"
      class="fixed
             inset-0
             z-[9999]
             flex
             items-center
             justify-center
             bg-[#faf9f7]/95
             px-6
             backdrop-blur-xl"
    >
      <div
        class="mx-auto
               w-full
               max-w-sm
               text-center"
      >
        <!-- BRAND -->
        <div
          class="text-[10px]
                 font-semibold
                 uppercase
                 tracking-[0.3em]
                 text-[#be5c2b]"
        >
          Castillo
        </div>

        <div
          class="mt-1
                 text-xl
                 font-semibold
                 tracking-tight
                 text-[#252525]"
        >
          Player
        </div>


        <!-- ICON -->
        <div
          class="mx-auto
                 mt-10
                 flex
                 h-24 w-24
                 items-center
                 justify-center
                 rounded-[32px]
                 bg-[#252525]
                 shadow-[0_20px_50px_rgba(0,0,0,.15)]"
        >
          <WifiOff
            class="h-10 w-10
                   text-[#ff6470]"
            :stroke-width="1.8"
          />
        </div>


        <h1
          class="mt-8
                 text-2xl
                 font-semibold
                 tracking-[-0.03em]
                 text-[#252525]"
        >
          Castillo Player sin conexión
        </h1>


        <p
          class="mx-auto
                 mt-3
                 max-w-xs
                 text-sm
                 leading-relaxed
                 text-black/40"
        >
          No se puede comunicar con
          la Raspberry Pi.
        </p>


        <!-- RECONNECT -->
        <div
          class="mt-8
                 inline-flex
                 items-center
                 gap-3
                 rounded-full
                 bg-[#f1eeeb]
                 px-5 py-3"
        >
          <RefreshCw
            class="h-4 w-4
                   animate-spin
                   text-[#be5c2b]"
          />

          <span
            class="text-xs
                   font-medium
                   text-black/50"
          >
            Intentando reconectar…
          </span>
        </div>


        <div
          class="mt-10
                 flex
                 items-center
                 justify-center
                 gap-2
                 text-[10px]
                 text-black/25"
        >
          <RadioTower
            class="h-4 w-4"
          />

          castilloplayer
        </div>
      </div>
    </div>
  </Transition>
</template>