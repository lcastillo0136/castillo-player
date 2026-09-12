<script setup>
import {
  computed
} from 'vue'

import {
  AudioLines,
  Disc3,
  Heart,
  ListMusic,
  Music2,
  Tags
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'


const {
  status,
  song,
  queue,

  coverUrl,
  isFavorite,
  toggleFavorite,

  playQueueItem
} = useCastilloApi()


const {
  navigate
} = useCastilloNavigation()


const currentQueueIndex = computed(() => {
  if (!queue.value.length) {
    return -1
  }

  if (song.value.id) {
    const byId =
      queue.value.findIndex(
        item =>
          String(item.id) ===
          String(song.value.id)
      )

    if (byId >= 0) {
      return byId
    }
  }

  return queue.value.findIndex(
    item =>
      item.file ===
      song.value.file
  )
})


const upcomingSongs = computed(() => {
  const index =
    currentQueueIndex.value

  if (index < 0) {
    return queue.value.slice(0, 4)
  }

  return queue.value.slice(
    index + 1,
    index + 5
  )
})


const year = computed(() => {
  const value =
    song.value.originaldate ||
    song.value.date ||
    ''

  const match =
    String(value).match(/^(\d{4})/)

  return match
    ? match[1]
    : ''
})

const trackNumber = computed(() => {
  const value =
    String(song.value.track || '')
      .split('/')[0]
      .trim()

  if (!value) {
    return ''
  }

  const number = Number.parseInt(
    value,
    10
  )

  if (!Number.isFinite(number)) {
    return value
  }

  return String(number).padStart(2, '0')
})

const audioInfo = computed(() => {
  const result = []

  if (status.value.bitrate) {
    result.push(
      `${status.value.bitrate} kbps`
    )
  }

  const format =
    status.value.audio ||
    song.value.format ||
    ''

  const match =
    String(format).match(
      /^(\d+):(\d+):(\d+)$/
    )

  if (match) {
    const hz = Number(match[1])

    const khz =
      hz >= 1000
        ? `${(hz / 1000).toFixed(
            hz % 1000 === 0 ? 0 : 1
          )} kHz`
        : `${hz} Hz`

    result.push(khz)
    result.push(`${match[2]} bit`)

    const channels =
      Number(match[3])

    if (channels === 1) {
      result.push('Mono')
    } else if (channels === 2) {
      result.push('Stereo')
    } else {
      result.push(
        `${channels} canales`
      )
    }
  }

  return result
})


function fileType(file) {
  if (!file) {
    return ''
  }

  const match =
    file.match(/\.([^.]+)$/)

  return match
    ? match[1].toUpperCase()
    : ''
}


function formatTime(value) {
  const total =
    Math.max(
      0,
      Math.floor(
        Number(value) || 0
      )
    )

  const minutes =
    Math.floor(total / 60)

  const seconds =
    total % 60

  return (
    `${minutes}:` +
    String(seconds).padStart(2, '0')
  )
}

function openCurrentArtist() {
  const artist =
    song.value?.artist

  if (!artist) {
    return
  }

  navigate(
    'artists',
    {
      artist
    }
  )
}


function openCurrentAlbum() {
  const album =
    song.value?.album

  if (!album) {
    return
  }

  navigate(
    'albums',
    {
      album,

      albumArtist:
        song.value?.albumartist ||
        song.value?.artist ||
        '—'
    }
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat(
    'es-MX'
  ).format(
    Number(value) || 0
  )
}
</script>


<template>
  <aside
    class="castillo-scroll
           hidden
           h-[calc(100vh-112px)]
           w-[330px]
           shrink-0
           overflow-y-auto
           border-l border-black/5
           bg-[#f2f1ef]
           px-6 py-7
           xl:block"
  >

    <!-- HEADER -->
    <div
      class="flex items-start
             justify-between"
    >
      <div>
        <p
          class="text-[10px]
                 font-medium
                 uppercase
                 tracking-[0.2em]
                 text-black/35"
        >
          Now Playing
        </p>

        <h2
          class="mt-1
                 text-lg
                 font-semibold"
        >
          Reproduciendo
        </h2>
      </div>

      <AudioLines
        v-if="
          song.file &&
          status.state === 'play'
        "
        class="castillo-audio-lines
               is-playing
               mt-1 h-5 w-5
               text-[#be5c2b]"
        :stroke-width="2"
      />

      <Music2
        v-else
        class="mt-1 h-5 w-5
               text-black/25"
        :stroke-width="1.8"
      />
    </div>


    <!-- COVER -->
    <div
      class="mt-6
             aspect-square
             overflow-hidden
             rounded-[30px]
             bg-[#dedbd8]
             shadow-[0_16px_40px_rgba(40,30,25,.08)]"
    >
      <img
        v-if="song.file"
        :src="coverUrl(song.file)"
        class="h-full w-full
               object-cover"
        alt="Carátula"
      />

      <div
        v-else
        class="flex h-full
               items-center
               justify-center"
      >
        <div class="text-center">
          <Music2
            class="mx-auto
                   h-10 w-10
                   text-black/15"
            :stroke-width="1.6"
          />

          <p
            class="mt-3
                   text-xs
                   text-black/30"
          >
            Sin reproducción
          </p>
        </div>
      </div>
    </div>


    <!-- TRACK INFO -->
    <div class="mt-6">

      <!-- TITLE + FAVORITE -->
      <div
        class="flex items-start
               justify-between
               gap-4"
      >
        <div class="min-w-0 flex-1">
          <h3
            class="text-[18px]
                   font-semibold
                   leading-[1.35]
                   tracking-[-0.015em]
                   text-[#252525]"
          >
            {{
              song.title ||
              'Sin reproducción'
            }}
          </h3>

          <button
            v-if="song.artist"
            class="block
                   max-w-full
                   truncate
                   text-left
                   text-sm
                   text-black/55
                   transition
                   hover:text-[#be5c2b]"
            title="Ver artista"
            @click="openCurrentArtist"
          >
            {{ song.artist }}
          </button>

          <span
            v-else
            class="text-sm
                   text-black/35"
          >
            —
          </span>

          <div
            class="mt-1 flex
                   min-w-0
                   items-center gap-2"
          >

            <button
              v-if="song.album"
              class="flex
                     max-w-full
                     items-center
                     gap-2
                     truncate
                     text-left
                     text-xs
                     text-black/35
                     transition
                     hover:text-[#be5c2b]
                     hover:underline
                     hover:underline-offset-4"
              title="Ver álbum"
              @click="openCurrentAlbum"
            >
              <Disc3
                class="h-4 w-4
                       shrink-0"
              />

              <span class="truncate">
                {{ song.album }}
              </span>
            </button>
          </div>
        </div>


        <button
          v-if="song.file"
          class="flex h-10 w-10
                 shrink-0
                 items-center
                 justify-center
                 rounded-full
                 transition
                 hover:bg-black/5"
          title="Favorito"
          @click="
            toggleFavorite(
              song.file
            )
          "
        >
          <Heart
            class="h-6 w-6"
            :class="
              isFavorite(song.file)
                ? 'text-[#be5c2b]'
                : 'text-black/30'
            "
            :fill="
              isFavorite(song.file)
                ? 'currentColor'
                : 'none'
            "
            :stroke-width="1.9"
          />
        </button>
      </div>


      <!-- MUSICAL METADATA -->
      <div
        v-if="
          trackNumber ||
          year
        "
        class="mt-4 flex
               items-center gap-2
               text-[10px]
               font-medium
               text-black/35"
      >
        <span
          v-if="trackNumber"
          class="rounded-full
                 bg-black/[0.04]
                 px-3 py-1.5"
        >
          # {{ trackNumber }}
        </span>

        <span
          v-if="
            trackNumber &&
            year
          "
          class="text-black/15"
        >
          •
        </span>

        <span
          v-if="year"
          class="rounded-full
                 bg-black/[0.04]
                 px-3 py-1.5"
        >
          {{ year }}
        </span>
      </div>


      <!-- DIVIDER -->
      <div
        v-if="song.file"
        class="my-5
               h-px
               bg-black/[0.055]"
      />


      <!-- AUDIO QUALITY -->
      <div
        v-if="song.file"
        class="rounded-[20px]
               bg-white/60
               px-4 py-3.5"
      >
        <div
          class="flex
                 items-start gap-3"
        >
          <div
            class="flex h-9 w-9
                   shrink-0
                   items-center
                   justify-center
                   rounded-xl
                   bg-[#fff1e9]
                   text-[#be5c2b]"
          >
            <AudioLines
              class="h-[18px] w-[18px]"
              :stroke-width="1.9"
            />
          </div>


          <div class="min-w-0 flex-1">
            <p
              class="text-[9px]
                     font-medium
                     uppercase
                     tracking-[0.14em]
                     text-black/30"
            >
              Calidad de audio
            </p>

            <p
              class="mt-1
                     text-[11px]
                     font-medium
                     text-black/60"
            >
              {{ fileType(song.file) }}

              <template
                v-if="status.bitrate"
              >
                · {{ status.bitrate }} kbps
              </template>
            </p>

            <p
              v-if="audioInfo.length"
              class="mt-1
                     text-[10px]
                     leading-relaxed
                     text-black/35"
            >
              {{ audioInfo.join(' · ') }}
            </p>
          </div>
        </div>
      </div>
    </div>


    <!-- ACTIONS -->
    <div
      v-if="song.file"
      class="mt-6 grid
             grid-cols-2 gap-2"
    >
      <button
        class="flex items-center
               justify-center
               gap-2
               rounded-2xl
               bg-[#252525]
               px-4 py-3
               text-xs
               font-medium
               text-white
               transition
               hover:bg-black"
        @click="
          navigate('queue')
        "
      >
        <ListMusic
          class="h-4 w-4"
          :stroke-width="2"
        />

        Ver cola
      </button>


      <button
        class="flex items-center
               justify-center
               gap-2
               rounded-2xl
               bg-white/70
               px-4 py-3
               text-xs
               font-medium
               text-black/55
               transition
               hover:bg-white
               hover:text-black"
        @click="
          navigate('lyrics')
        "
      >
        <Music2
          class="h-4 w-4"
          :stroke-width="2"
        />

        Ver letras
      </button>

      <button
        class="flex
               items-center
               gap-2
               rounded-full
               bg-black/[0.04]
               px-3 py-2
               text-xs
               font-medium
               text-black/50
               transition
               hover:bg-black/[0.07]
               hover:text-black/70"
        @click="
          navigate(
            'tags-edit'
          )
        "
      >
        <Tags
          class="h-4 w-4"
        />

        Editar tags
      </button>
    </div>


    <!-- NEXT SONGS -->
    <section class="mt-8">
      <div
        class="flex items-center
               justify-between"
      >
        <h4
          class="text-sm
                 font-semibold"
        >
          Siguiente
        </h4>

        <button
          class="text-[10px]
                 text-black/35
                 hover:text-black/60"
          @click="
            navigate('queue')
          "
        >
          {{ formatNumber(queue.length) }} en cola
        </button>
      </div>


      <div
        v-if="upcomingSongs.length"
        class="mt-4 space-y-2"
      >
        <button
          v-for="item in upcomingSongs"
          :key="
            item.id ||
            item.file
          "
          class="group
                 flex w-full
                 items-center
                 gap-3
                 rounded-2xl
                 p-2
                 text-left
                 transition
                 hover:bg-black/[0.035]"
          @click="
            playQueueItem(
              item.id
            )
          "
        >
          <img
            :src="
              coverUrl(item.file)
            "
            class="h-11 w-11
                   shrink-0
                   rounded-xl
                   object-cover"
          />

          <div
            class="min-w-0
                   flex-1"
          >
            <p
              class="truncate
                     text-xs
                     font-medium"
            >
              {{
                item.title ||
                item.file
                  .split('/')
                  .pop()
              }}
            </p>

            <p
              class="mt-1
                     truncate
                     text-[10px]
                     text-black/35"
            >
              {{ item.artist || '—' }}
            </p>
          </div>

          <span
            class="text-[10px]
                   tabular-nums
                   text-black/25"
          >
            {{
              formatTime(
                item.duration ||
                item.time
              )
            }}
          </span>
        </button>
      </div>


      <div
        v-else
        class="mt-4
               rounded-2xl
               bg-white/40
               px-4 py-5
               text-center"
      >
        <ListMusic
          class="mx-auto
                 h-5 w-5
                 text-black/20"
          :stroke-width="1.8"
        />

        <p
          class="mt-2
                 text-[11px]
                 leading-relaxed
                 text-black/30"
        >
          No hay más canciones
          en la cola.
        </p>
      </div>
    </section>

  </aside>
</template>