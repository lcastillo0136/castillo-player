<script setup>
import {
  onBeforeUnmount,
  onMounted,
  ref
} from 'vue'

import {
  Check,
  ChevronDown,
  Headphones,
  LoaderCircle,
  Music2,
  Speaker
} from 'lucide-vue-next'

import {
  useAudioOutput
} from '../composables/useAudioOutput'


const root = ref(null)

const opened = ref(false)


const {
  outputs,
  currentOutput,

  loading,
  changing,
  error,

  loadAudioOutputs,
  selectAudioOutput
} = useAudioOutput()


async function toggle() {
  opened.value =
    !opened.value

  if (opened.value) {
    await loadAudioOutputs(
      true
    )
  }
}


async function selectOutput(
  output
) {
  if (
    output.id ===
    currentOutput.value?.id
  ) {
    opened.value = false
    return
  }


  try {
    await selectAudioOutput(
      output
    )

    opened.value = false

  } catch {
    /*
     * Dejamos abierto el selector
     * para que el usuario vea el error.
     */
  }
}


function closeOutside(event) {
  if (
    root.value &&
    !root.value.contains(
      event.target
    )
  ) {
    opened.value = false
  }
}


onMounted(() => {
  loadAudioOutputs()

  document.addEventListener(
    'pointerdown',
    closeOutside
  )
})


onBeforeUnmount(() => {
  document.removeEventListener(
    'pointerdown',
    closeOutside
  )
})
</script>


<template>
  <div
    ref="root"
    class="relative
           inline-block
           max-w-full"
  >
    <!-- PILL -->
    <button
      class="flex
             h-12
             max-w-full
             items-center
             gap-2.5
             rounded-full
             bg-[#252525]
             px-4
             text-white
             shadow-[0_10px_28px_rgba(0,0,0,.16)]
             transition
             hover:bg-[#303030]
             active:scale-[0.98]"
      @click="toggle"
    >
      <LoaderCircle
        v-if="
          loading ||
          changing
        "
        class="h-5 w-5
               shrink-0
               animate-spin
               text-[#ff6470]"
      />

      <Music2
        v-else-if="
          currentOutput?.type ===
          'local'
        "
        class="h-5 w-5
               shrink-0
               text-[#ff6470]"
      />

      <Headphones
        v-else
        class="h-5 w-5
               shrink-0
               text-[#ff6470]"
      />


      <span
        class="max-w-[160px]
               truncate
               text-sm
               font-medium"
      >
        {{
          currentOutput?.name ||
          'Salida de audio'
        }}
      </span>


      <ChevronDown
        class="h-4 w-4
               shrink-0
               text-white/50
               transition"
        :class="
          opened
            ? 'rotate-180'
            : ''
        "
      />
    </button>


    <!-- DROPDOWN -->
    <div
      v-if="opened"
      class="absolute
             bottom-[calc(100%+10px)]
             left-1/2
             z-[250]
             w-[280px]
             max-w-[calc(100vw-32px)]
             -translate-x-1/2
             overflow-hidden
             rounded-[24px]
             border
             border-black/[0.05]
             bg-white/95
             p-2
             text-[#252525]
             shadow-[0_22px_60px_rgba(0,0,0,.18)]
             backdrop-blur-xl"
    >
      <div
        class="px-3
               pb-2
               pt-2"
      >
        <p
          class="text-[9px]
                 font-semibold
                 uppercase
                 tracking-[0.18em]
                 text-black/30"
        >
          Salida de audio
        </p>
      </div>


      <button
        v-for="output in outputs"
        :key="output.id"

        class="flex
               w-full
               items-center
               gap-3
               rounded-[18px]
               px-3 py-3
               text-left
               transition
               hover:bg-black/[0.04]"

        :class="
          output.id ===
          currentOutput?.id
            ? 'bg-[#fff1ec]'
            : ''
        "

        @click="
          selectOutput(
            output
          )
        "
      >
        <div
          class="flex
                 h-10 w-10
                 shrink-0
                 items-center
                 justify-center
                 rounded-2xl"
          :class="
            output.id ===
            currentOutput?.id
              ? 'bg-[#ff6470]/10 text-[#ff6470]'
              : 'bg-black/[0.04] text-black/45'
          "
        >
          <Music2
            v-if="
              output.type ===
              'local'
            "
            class="h-5 w-5"
          />

          <Speaker
            v-else
            class="h-5 w-5"
          />
        </div>


        <div
          class="min-w-0 flex-1"
        >
          <p
            class="truncate
                   text-sm
                   font-semibold"
          >
            {{ output.name }}
          </p>

          <p
            class="mt-0.5
                   truncate
                   text-[10px]
                   text-black/35"
          >
            <template
              v-if="
                output.type ===
                'local'
              "
            >
              Salida local
            </template>

            <template v-else>
              Bluetooth

              <span
                v-if="
                  output.connected
                "
                class="text-[#be5c2b]"
              >
                · conectado
              </span>
            </template>
          </p>
        </div>


        <Check
          v-if="
            output.id ===
            currentOutput?.id
          "
          class="h-5 w-5
                 shrink-0
                 text-[#ff6470]"
          :stroke-width="2.4"
        />
      </button>


      <p
        v-if="error"
        class="mx-3
               mb-2 mt-1
               rounded-xl
               bg-red-50
               px-3 py-2
               text-[10px]
               leading-relaxed
               text-red-600"
      >
        {{ error }}
      </p>
    </div>
  </div>
</template>