<script setup>
import {
  onBeforeUnmount,
  onMounted,
  ref
} from 'vue'

import {
  Disc3,
  HardDrive,
  Hash,
  Heart,
  House,
  ListChecks,
  ListMusic,
  Music2,
  PanelLeftClose,
  PanelLeftOpen,
  RefreshCw,
  Search,
  Settings,
  UsersRound
} from 'lucide-vue-next'


defineProps({
  currentView: {
    type: String,
    required: true
  }
})


const emit = defineEmits([
  'navigate'
])


const STORAGE_KEY =
  'castillo.sidebar.collapsed'

const collapsed = ref(false)
const nasSync = ref(null)
const usbStorage = ref(null)
const clockTick = ref(0)

let nasTimer = null
let storageTimer = null

const items = [
  {
    id: 'home',
    label: 'Inicio',
    icon: House
  },
  {
    id: 'search',
    label: 'Buscar',
    icon: Search
  },
  {
    id: 'library',
    label: 'Canciones',
    icon: Music2
  },
  {
    id: 'albums',
    label: 'Álbumes',
    icon: Disc3
  },
  {
    id: 'artists',
    label: 'Artistas',
    icon: UsersRound
  },
  {
    id: 'queue',
    label: 'Cola',
    icon: ListMusic
  },
  {
    id: 'favorites',
    label: 'Favoritos',
    icon: Heart
  },
  {
    id: 'hashtags',
    label: 'Hashtags',
    icon: Hash
  },
  {
    id: 'pending',
    label: 'Cambios pendientes',
    icon: ListChecks
  }
]

function applySidebarWidth() {
  document.documentElement.style.setProperty(
    '--castillo-sidebar-width',
    collapsed.value
      ? '64px'
      : '250px'
  )
}

function toggleSidebar() {
  collapsed.value =
    !collapsed.value

  localStorage.setItem(
    STORAGE_KEY,
    collapsed.value
      ? '1'
      : '0'
  )

  applySidebarWidth()
}

async function loadNasSyncStatus() {
  try {
    const response =
      await fetch(
        '/castillo-api/nas-sync-status.php',
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer el estado NAS.'
      )
    }


    nasSync.value =
      data


  } catch {
    nasSync.value = {
      ok: false
    }
  }
}

async function loadUsbStorage() {
  try {
    const response =
      await fetch(
        '/castillo-api/storage.php',
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible leer el almacenamiento.'
      )
    }


    usbStorage.value =
      data


  } catch {
    usbStorage.value = {
      ok: false
    }
  }
}


function formatStorageSize(
  bytes
) {
  const value =
    Number(bytes)


  if (
    !Number.isFinite(value) ||
    value < 0
  ) {
    return '—'
  }


  const gib =
    value /
    (
      1024 *
      1024 *
      1024
    )


  if (gib >= 1024) {
    return (
      gib / 1024
    ).toFixed(1) + ' TB'
  }


  return (
    gib.toFixed(1) +
    ' GB'
  )
}

function formatRemaining() {
  void clockTick.value
  if (
    !nasSync.value?.ok
  ) {
    return 'Estado no disponible'
  }


  if (
    !nasSync.value.has_history
  ) {
    return 'Sin sincronizaciones'
  }


  const target =
    Number(
      nasSync.value.next_sync_epoch
    ) * 1000


  let seconds =
    Math.max(
      0,
      Math.floor(
        (
          target -
          Date.now()
        ) / 1000
      )
    )


  if (seconds <= 0) {
    return 'Pendiente de actualizar'
  }


  const days =
    Math.floor(
      seconds / 86400
    )

  seconds %=
    86400


  const hours =
    Math.floor(
      seconds / 3600
    )

  seconds %=
    3600


  const minutes =
    Math.floor(
      seconds / 60
    )


  if (days > 0) {
    return (
      `${days} d ${hours} h`
    )
  }


  if (hours > 0) {
    return (
      `${hours} h ${minutes} min`
    )
  }


  return (
    `${minutes} min`
  )
}

function formatNextSync() {
  if (
    !nasSync.value?.next_sync_epoch
  ) {
    return ''
  }


  const date =
    new Date(
      Number(
        nasSync.value.next_sync_epoch
      ) * 1000
    )


  return new Intl.DateTimeFormat(
    'es-MX',
    {
      day: 'numeric',
      month: 'short',
      hour: 'numeric',
      minute: '2-digit'
    }
  ).format(
    date
  )
}

onMounted(() => {
  collapsed.value =
    localStorage.getItem(
      STORAGE_KEY
    ) === '1'

  applySidebarWidth()

  loadNasSyncStatus()

  /*
   * Refrescar la información del backend
   * periódicamente.
   */
  nasTimer =
    window.setInterval(
      () => {
        clockTick.value++

        loadNasSyncStatus()
      },
      60_000
    )
})
onBeforeUnmount(() => {
  if (nasTimer) {
    window.clearInterval(
      nasTimer
    )
  }
})
onMounted(() => {
  loadUsbStorage()

  storageTimer =
    window.setInterval(
      loadUsbStorage,
      60000
    )
})


onBeforeUnmount(() => {
  if (storageTimer) {
    window.clearInterval(
      storageTimer
    )
  }
})
</script>


<template>
  <aside
    class="relative hidden
           h-screen
           shrink-0
           flex-col
           overflow-visible
           bg-[#625d5a]
           py-7
           text-white
           transition-[width,padding]
           duration-300
           ease-out
           lg:flex"
    :class="
      collapsed
        ? 'px-2'
        : 'px-5'
    "
    :style="{
      width:
        'var(--castillo-sidebar-width)'
    }"
  >
    <!-- BRAND / COLLAPSE -->
    <div
      class="mb-9"
      :class="
        collapsed
          ? 'px-0'
          : 'px-3'
      "
    >
      <div
        class="relative
               rounded-[18px]
               "
        :class="
          collapsed
            ? 'flex h-[50px] items-center justify-center'
            : 'border border-white/10 bg-black/10 backdrop-blur-sm min-h-[84px] px-4 py-4'
        "
      >
        <!-- EXPANDED BRAND -->
        <div
          v-if="!collapsed"
          class="min-w-0 pr-10"
        >
          <div
            class="text-[11px]
                   font-semibold
                   uppercase
                   tracking-[0.34em]
                   text-[#f2f478]"
          >
            Castillo
          </div>

          <div
            class="mt-1
                   text-2xl
                   font-semibold
                   tracking-tight
                   text-white"
          >
            Player
          </div>
        </div>

        <!-- COLLAPSED BRAND -->
        <div
          v-else
          class="flex
                 h-10 w-10
                 items-center
                 justify-center
                 rounded-[14px]
                 text-base
                 font-semibold
                 text-[#f2f478]"
        >
          C
        </div>

        <!-- COLLAPSE / EXPAND BUTTON -->
        <button
          class="absolute
                 right-2 top-2
                 flex
                 h-8 w-8
                 items-center
                 justify-center
                 rounded-xl
                 border border-white/10
                 bg-[#6e6966]
                 text-white/70
                 shadow-[0_6px_14px_rgba(0,0,0,.16)]
                 transition
                 hover:bg-[#7b7571]
                 hover:text-white
                 active:scale-95"
          :title="
            collapsed
              ? 'Abrir barra lateral'
              : 'Contraer barra lateral'
          "
          @click="toggleSidebar"
        >
          <PanelLeftOpen
            v-if="collapsed"
            class="h-4 w-4"
          />

          <PanelLeftClose
            v-else
            class="h-4 w-4"
          />
        </button>
      </div>
    </div>

    <!-- NAVIGATION -->
    <nav class="space-y-1">
      <button
        v-for="item in items"
        :key="item.id"
        class="group
               relative
               flex
               h-11
               w-full
               items-center
               rounded-[14px]
               text-left
               text-sm
               transition"
        :class="[
          collapsed
            ? 'justify-center px-0'
            : 'gap-3 px-4',

          currentView === item.id
            ? 'bg-[#252525] text-[#f2f478]'
            : 'text-white/70 hover:bg-white/10 hover:text-white'
        ]"
        :aria-label="item.label"
        @click="
          emit(
            'navigate',
            item.id
          )
        "
      >
        <component
          :is="item.icon"
          class="h-5 w-5
                 shrink-0"
        />

        <span
          v-if="!collapsed"
          class="truncate"
        >
          {{ item.label }}
        </span>


        <!-- TOOLTIP COLLAPSED -->
        <span
          v-if="collapsed"
          class="pointer-events-none
                 absolute
                 left-[calc(100%+12px)]
                 top-1/2
                 z-[500]
                 -translate-y-1/2
                 translate-x-1
                 whitespace-nowrap
                 rounded-xl
                 bg-[#252525]
                 px-3 py-2
                 text-xs
                 font-medium
                 text-white
                 opacity-0
                 shadow-xl
                 transition
                 group-hover:translate-x-0
                 group-hover:opacity-100"
        >
          {{ item.label }}
        </span>
      </button>
    </nav>

    <!-- CONFIGURATION -->
    <div
      class="mt-auto"
      :class="
        collapsed
          ? ''
          : ''
      "
    >
      <button
        class="group
               relative
               flex
               h-11
               w-full
               items-center
               rounded-[14px]
               text-sm
               transition"
        :class="[
          collapsed
            ? 'justify-center px-0'
            : 'gap-3 px-4',

          currentView === 'settings'
            ? 'bg-[#252525] text-[#f2f478]'
            : 'text-white/60 hover:bg-white/10 hover:text-white'
        ]"
        @click="
          emit(
            'navigate',
            'settings'
          )
        "
      >
        <Settings
          class="h-5 w-5
                 shrink-0"
        />

        <span
          v-if="!collapsed"
          class="truncate"
        >
          Configuración
        </span>


        <!-- COLLAPSED TOOLTIP -->
        <span
          v-if="collapsed"
          class="pointer-events-none
                 absolute
                 left-[calc(100%+12px)]
                 top-1/2
                 z-[500]
                 -translate-y-1/2
                 translate-x-1
                 whitespace-nowrap
                 rounded-xl
                 bg-[#252525]
                 px-3 py-2
                 text-xs
                 font-medium
                 text-white
                 opacity-0
                 shadow-xl
                 transition
                 group-hover:translate-x-0
                 group-hover:opacity-100"
        >
          Configuración
        </span>
      </button>
    </div>

    <!-- USB STATUS -->
    <div
      class="mt-3"
      :class="
        collapsed
          ? ''
          : 'px-3'
      "
    >
      <!-- EXPANDED -->
      <div
        v-if="!collapsed"
        class="rounded-2xl
               border
               border-white/10
               bg-black/10
               p-4"
      >
        <!-- USB -->
        <div
          class="flex
                 items-center
                 gap-2
                 text-xs
                 text-white/60"
        >
          <span
            class="h-2 w-2
                   rounded-full
                   bg-emerald-400"
          />

          USB Local
        </div>


        <p
          class="mt-2
                 text-[11px]
                 leading-relaxed
                 text-white/40"
        >
          Biblioteca local de
          Castillo Player
        </p>

        <!-- USB STORAGE -->
        <div
          v-if="usbStorage?.ok"
          class="mt-3"
        >
          <div
            class="text-[10px]
                   text-white/45"
          >
            <div
              class="flex
                     items-center
                     justify-between
                     gap-2"
            >
              <span>
                Almacenamiento
              </span>

              <span
                class="text-right
                       text-[11px]
                       font-medium
                       leading-tight
                       text-white/65"
              >
                {{
                  formatStorageSize(
                    usbStorage.used_bytes
                  )
                }}
              </span>
            </div>

            <div
              class="mt-0.5
                     text-right
                     text-[10px]
                     leading-tight
                     text-white/45"
            >
              de
              {{
                formatStorageSize(
                  usbStorage.total_bytes
                )
              }}
            </div>
          </div>


          <div
            class="mt-2
                   h-1.5
                   overflow-hidden
                   rounded-full
                   bg-white/10"
          >
            <div
              class="h-full
                     rounded-full
                     bg-emerald-400
                     transition-all
                     duration-500"
              :style="{
                width:
                  `${usbStorage.used_percent}%`
              }"
            />
          </div>


          <div
            class="mt-1.5
                   flex
                   items-center
                   justify-between
                   text-[9px]
                   text-white/35"
          >
            <span>
              {{ usbStorage.used_percent }}%
              usado
            </span>

            <span>
              {{
                formatStorageSize(
                  usbStorage.free_bytes
                )
              }}
              libres
            </span>
          </div>
        </div>

        <!-- DIVIDER -->
        <div
          class="my-4
                 h-px
                 bg-white/[0.08]"
        />


        <!-- NAS -->
        <div
          class="flex
                 items-start
                 gap-2.5"
        >
          <RefreshCw
            class="mt-0.5
                   h-4 w-4
                   shrink-0
                   text-[#f2f478]"
            :stroke-width="2"
          />


          <div
            class="min-w-0
                   flex-1"
          >
            <p
              class="text-[11px]
                     font-medium
                     text-white/65"
            >
              NAS → USB
            </p>


            <p
              class="mt-1
                     text-[10px]
                     text-white/35"
            >
              Próxima actualización
            </p>


            <p
              class="mt-0.5
                     text-sm
                     font-semibold
                     text-[#f2f478]"
            >
              {{ formatRemaining() }}
            </p>


            <p
              v-if="
                nasSync?.next_sync_epoch
              "
              class="mt-1
                     text-[10px]
                     text-white/30"
            >
              {{ formatNextSync() }}
            </p>
          </div>
        </div>
      </div>


      <!-- COLLAPSED -->
      <div
        v-else
        class="group
               relative
               flex
               h-11
               items-center
               justify-center
               rounded-2xl
               border
               border-white/10
               bg-black/10"
      >
        <div class="relative">
          <HardDrive
            class="h-5 w-5
                   text-white/55"
          />

          <span
            class="absolute
                   -right-1
                   -top-1
                   h-2 w-2
                   rounded-full
                   bg-emerald-400
                   ring-2
                   ring-[#625d5a]"
          />
        </div>


        <span
          class="pointer-events-none
                 absolute
                 left-[calc(100%+12px)]
                 top-1/2
                 z-[500]
                 -translate-y-1/2
                 translate-x-1
                 whitespace-nowrap
                 rounded-xl
                 bg-[#252525]
                 px-3 py-2
                 text-xs
                 font-medium
                 text-white
                 opacity-0
                 shadow-xl
                 transition
                 group-hover:translate-x-0
                 group-hover:opacity-100"
        >
          USB Local
        </span>
      </div>
    </div>
  </aside>
</template>