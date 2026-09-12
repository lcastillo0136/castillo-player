<script setup>
import {
  AlertTriangle,
  Check,
  Loader2,
  X
} from 'lucide-vue-next'

import {
  useNasSync
} from '../composables/useNasSync'


const {
  syncing,
  syncingCount,

  syncResult,
  syncError,

  clearSyncResult
} = useNasSync()
</script>


<template>
  <div
    class="pointer-events-none
           fixed
           left-1/2
           top-4
           z-[1000]
           -translate-x-1/2"
  >
    <Transition
      enter-active-class="
        transition
        duration-200
        ease-out
      "
      enter-from-class="
        -translate-y-2
        opacity-0
        scale-95
      "
      enter-to-class="
        translate-y-0
        opacity-100
        scale-100
      "
      leave-active-class="
        transition
        duration-150
        ease-in
      "
      leave-from-class="
        translate-y-0
        opacity-100
        scale-100
      "
      leave-to-class="
        -translate-y-2
        opacity-0
        scale-95
      "
      mode="out-in"
    >
      <!-- SYNCING -->
      <div
        v-if="syncing"
        key="syncing"
        class="pointer-events-auto
               flex
               min-w-[230px]
               items-center
               gap-3
               rounded-[18px]
               border
               border-white/10
               bg-[#252525]
               px-4 py-3
               text-white
               shadow-2xl"
      >
        <div
          class="flex
                 h-9 w-9
                 shrink-0
                 items-center
                 justify-center
                 rounded-[12px]
                 bg-white/10"
        >
          <Loader2
            class="h-4 w-4
                   animate-spin
                   text-[#f2f478]"
          />
        </div>


        <div
          class="min-w-0"
        >
          <p
            class="text-xs
                   font-semibold"
          >
            Sincronizando con NAS…
          </p>

          <p
            class="mt-0.5
                   text-[10px]
                   text-white/45"
          >
            {{ syncingCount }}
            archivo{{
              syncingCount === 1
                ? ''
                : 's'
            }}
          </p>
        </div>
      </div>


      <!-- ERROR -->
      <div
        v-else-if="syncError"
        key="error"
        class="pointer-events-auto
               flex
               min-w-[260px]
               max-w-[420px]
               items-center
               gap-3
               rounded-[18px]
               border
               border-red-200
               bg-red-50
               px-4 py-3
               shadow-2xl"
      >
        <div
          class="flex
                 h-9 w-9
                 shrink-0
                 items-center
                 justify-center
                 rounded-[12px]
                 bg-red-100"
        >
          <AlertTriangle
            class="h-4 w-4
                   text-red-600"
          />
        </div>


        <div
          class="min-w-0
                 flex-1"
        >
          <p
            class="text-xs
                   font-semibold
                   text-red-700"
          >
            Error al sincronizar
          </p>

          <p
            class="mt-0.5
                   text-[10px]
                   leading-relaxed
                   text-red-600/70"
          >
            {{ syncError }}
          </p>
        </div>


        <button
          class="flex
                 h-7 w-7
                 shrink-0
                 items-center
                 justify-center
                 rounded-full
                 text-red-500
                 hover:bg-red-100"
          @click="clearSyncResult"
        >
          <X
            class="h-4 w-4"
          />
        </button>
      </div>


      <!-- SUCCESS -->
      <div
        v-else-if="
          syncResult?.ok
        "
        key="success"
        class="pointer-events-auto
               flex
               min-w-[230px]
               items-center
               gap-3
               rounded-[18px]
               border
               border-emerald-200
               bg-emerald-50
               px-4 py-3
               shadow-2xl"
      >
        <div
          class="flex
                 h-9 w-9
                 shrink-0
                 items-center
                 justify-center
                 rounded-[12px]
                 bg-emerald-100"
        >
          <Check
            class="h-4 w-4
                   text-emerald-700"
          />
        </div>


        <div
          class="min-w-0
                 flex-1"
        >
          <p
            class="text-xs
                   font-semibold
                   text-emerald-800"
          >
            NAS actualizado
          </p>

          <p
            class="mt-0.5
                   text-[10px]
                   text-emerald-700/65"
          >
            {{
              syncResult.synced || 0
            }}
            archivo{{
              syncResult.synced === 1
                ? ''
                : 's'
            }}
            sincronizado{{
              syncResult.synced === 1
                ? ''
                : 's'
            }}
          </p>
        </div>


        <button
          class="flex
                 h-7 w-7
                 shrink-0
                 items-center
                 justify-center
                 rounded-full
                 text-emerald-700
                 hover:bg-emerald-100"
          @click="clearSyncResult"
        >
          <X
            class="h-4 w-4"
          />
        </button>
      </div>
    </Transition>
  </div>
</template>