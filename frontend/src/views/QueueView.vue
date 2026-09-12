<script setup>
import {
  computed
} from 'vue'

import {
  ArrowDown,
  ArrowUp,
  AudioLines,
  ListMusic,
  Play,
  Trash2,
  Volume2
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

const {
  status,
  song,
  playing,
  queue,

  coverUrl,

  clearQueue,
  playQueueItem,
  removeQueueItem,
  moveQueueItem
} = useCastilloApi()


const totalDuration = computed(() => {
  return queue.value.reduce(
    (total, item) => {
      return total +
        Number(
          item.duration ||
          item.time ||
          0
        )
    },
    0
  )
})

const isCurrentTrackPlaying = computed(() => {
  return (
    song.value &&
    playing.value
  )
})


function formatTime(value) {
  const total =
    Math.max(
      0,
      Math.floor(
        Number(value) || 0
      )
    )

  const hours =
    Math.floor(total / 3600)

  const minutes =
    Math.floor(
      (total % 3600) / 60
    )

  const seconds =
    total % 60

  if (hours > 0) {
    return (
      `${hours}:` +
      String(minutes).padStart(2, '0') +
      ':' +
      String(seconds).padStart(2, '0')
    )
  }

  return (
    `${minutes}:` +
    String(seconds).padStart(2, '0')
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat(
    'es-MX'
  ).format(
    Number(value) || 0
  )
}


function isCurrent(item) {
  if (
    item.id &&
    song.value.id
  ) {
    return (
      String(item.id) ===
      String(song.value.id)
    )
  }

  return (
    item.file ===
    song.value.file
  )
}


async function moveUp(
  item,
  index
) {
  if (index <= 0) {
    return
  }

  await moveQueueItem(
    item.id,
    index - 1
  )
}


async function moveDown(
  item,
  index
) {
  if (
    index >=
    queue.value.length - 1
  ) {
    return
  }

  await moveQueueItem(
    item.id,
    index + 1
  )
}


async function clearAll() {
  if (!queue.value.length) {
    return
  }

  const confirmed =
    window.confirm(
      '¿Vaciar toda la cola de reproducción?'
    )

  if (!confirmed) {
    return
  }

  await clearQueue()
}
</script>

<template>
  <div>
    <!-- HEADER -->
    <div
      class="flex flex-wrap
             items-end justify-between
             gap-5"
    >
      <div>
        <p
          class="text-xs font-medium
                 uppercase
                 tracking-[0.18em]
                 text-[#be5c2b]"
        >
          Reproducción
        </p>

        <h1
          class="mt-1 text-3xl
                 font-semibold
                 tracking-tight"
        >
          Cola
        </h1>

        <p
          class="mt-2 text-sm
                 text-black/35"
        >
          {{ formatNumber(queue.length) }}
          canciones

          <template
            v-if="totalDuration"
          >
            ·
            {{
              formatTime(
                totalDuration
              )
            }}
          </template>
        </p>
      </div>

      <button
        v-if="queue.length"
        class="flex items-center
               gap-2 rounded-full
               bg-red-50
               px-5 py-2.5
               text-xs font-medium
               text-red-600
               transition
               hover:bg-red-100"
        @click="clearAll"
      >
        <Trash2
          class="h-4 w-4"
          :stroke-width="2"
        />

        Vaciar cola
      </button>
    </div>


    <!-- CONSUME -->
    <div
      v-if="status.consume === '1'"
      class="mt-6 rounded-2xl
             border border-[#be5c2b]/10
             bg-[#fff3eb]
             px-4 py-3
             text-xs leading-relaxed
             text-[#8a4827]"
    >
      MPD tiene activado el modo
      <strong>Consume</strong>:
      las canciones terminadas se eliminan
      automáticamente de la cola.
    </div>


    <!-- EMPTY -->
    <div
      v-if="!queue.length"
      class="mt-14 flex
             flex-col items-center
             justify-center
             text-center"
    >
      <div
        class="flex h-20 w-20
               items-center
               justify-center
               rounded-[28px]
               bg-[#f1eeeb]"
      >
        <ListMusic 
          class="h-8 w-8
                 text-black/25"
          :stroke-width="2"
        />
      </div>

      <h2
        class="mt-5
               text-lg font-semibold"
      >
        La cola está vacía
      </h2>

      <p
        class="mt-2 max-w-sm
               text-sm leading-relaxed
               text-black/35"
      >
        Agrega canciones desde la
        biblioteca, búsqueda, favoritos
        o playlists.
      </p>
    </div>


    <!-- QUEUE -->
    <div
      v-else
      class="mt-7 space-y-1"
    >
      <div
        v-for="(item, index) in queue"
        :key="item.id || item.file"
        class="group grid
               grid-cols-[34px_48px_minmax(0,1fr)_auto]
               items-center gap-3
               rounded-2xl
               px-3 py-2.5
               transition
               sm:grid-cols-[34px_52px_minmax(0,1.5fr)_minmax(100px,.8fr)_70px_auto]"
        :class="
          isCurrent(item)
            ? 'bg-[#fff1e9] ring-1 ring-[#ff6470]/10'
            : 'hover:bg-black/[0.035]'
        "
      >
        <!-- POSITION -->
        <div
          class="flex items-center
                 justify-center"
        >
          <Volume2
            v-if="isCurrent(item)"
            class="h-4 w-4
                   text-[#be5c2b]"
          />

          <span
            v-else
            class="text-xs
                   tabular-nums
                   text-black/25"
          >
            {{ index + 1 }}
          </span>
        </div>


        <!-- COVER -->
        <button
          class="relative h-12 w-12
                 overflow-hidden
                 rounded-xl
                 bg-[#ece8e4]"
          @click="
            playQueueItem(item.id)
          "
        >
          <img
            :src="coverUrl(item.file)"
            class="h-full w-full
                   object-cover"
          />
          <!-- CANCIÓN ACTUAL -->
          <span
            v-if="isCurrent(item)"
            class="absolute inset-0
                   flex items-center justify-center
                   bg-white/90"
          >
            <AudioLines
              class="castillo-audio-lines
                     h-6 w-6
                     text-[#252525]"
              :class="{
                'is-playing':
                  isCurrentTrackPlaying
              }"
              :stroke-width="2"
            />
          </span>

          <span
            v-else
            class="absolute inset-0
                   flex items-center
                   justify-center
                   bg-black/30
                   opacity-0
                   transition
                   group-hover:opacity-100"
          >
            <Play
              class="h-5 w-5
                     text-white"
              :stroke-width="2.2"
            />
          </span>
        </button>


        <!-- TITLE -->
        <div class="min-w-0">
          <p
            class="truncate
                   text-sm font-medium"
            :class="
              isCurrent(item)
                ? 'text-[#be5c2b]'
                : ''
            "
          >
            {{
              item.title ||
              item.file
                .split('/')
                .pop()
            }}
          </p>

          <p
            class="mt-0.5 truncate
                   text-xs
                   text-black/35
                   sm:hidden"
          >
            {{ item.artist || '—' }}
          </p>
        </div>


        <!-- ARTIST -->
        <p
          class="hidden truncate
                 text-xs
                 text-black/40
                 sm:block"
        >
          {{ item.artist || '—' }}
        </p>


        <!-- DURATION -->
        <p
          class="hidden text-right
                 text-xs
                 tabular-nums
                 text-black/30
                 sm:block"
        >
          {{
            formatTime(
              item.duration ||
              item.time
            )
          }}
        </p>


        <!-- ACTIONS -->
        <div
          class="flex items-center
                 justify-end gap-0.5"
        >
          <button
            class="rounded-full p-2
                   text-black/30
                   transition
                   hover:bg-black/5
                   hover:text-black
                   disabled:opacity-15"
            title="Subir"
            :disabled="index === 0"
            @click="
              moveUp(item, index)
            "
          >
            <ArrowUp
              class="h-4 w-4"
              :stroke-width="2"
            />
          </button>

          <button
            class="rounded-full p-2
                   text-black/30
                   transition
                   hover:bg-black/5
                   hover:text-black
                   disabled:opacity-15"
            title="Bajar"
            :disabled="
              index ===
              queue.length - 1
            "
            @click="
              moveDown(item, index)
            "
          >
            <ArrowDown
              class="h-4 w-4"
              :stroke-width="2"
            />
          </button>

          <button
            class="rounded-full p-2
                   text-black/30
                   transition
                   hover:bg-red-50
                   hover:text-red-600"
            title="Eliminar de la cola"
            @click="
              removeQueueItem(
                item.id
              )
            "
          >
            <Trash2
              class="h-4 w-4"
              :stroke-width="2"
            />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>