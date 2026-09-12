<script setup>
import {
  computed
} from 'vue'

import {
  Album,
  ArrowRight,
  Disc3,
  Clock3,
  Hash,
  Heart,
  LibraryBig,
  Music2,
  Pause,
  Play,
  Rows3,
  Sparkles
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

import {
  useListeningHistory
} from '../composables/useListeningHistory'

import WaveformProgress
  from '../components/WaveformProgress.vue'

const {
  song,
  library,
  playing,
  elapsed,
  currentDuration,

  coverUrl,

  togglePlayback,
  playFile,
  playAll,
  seek
} = useCastilloApi()

const {
  navigate
} = useCastilloNavigation()

const {
  history
} = useListeningHistory()

const recentSongs = computed(() => {
  return history.value.slice(
    0,
    6
  )
})

const featuredAlbums = computed(() => {
  return (
    library.value?.albums || []
  ).slice(0, 6)
})


const greeting = computed(() => {
  const hour =
    new Date().getHours()

  if (hour < 12) {
    return 'Buenos días'
  }

  if (hour < 19) {
    return 'Buenas tardes'
  }

  return 'Buenas noches'
})


const totalSongs = computed(() => {
  return (
    library.value?.total ||
    library.value?.songs?.length ||
    0
  )
})


const totalAlbums = computed(() => {
  return (
    library.value?.albums?.length ||
    0
  )
})


const totalArtists = computed(() => {
  return (
    library.value?.artists?.length ||
    0
  )
})


function openLyrics() {
  if (!song.value?.file) {
    return
  }

  navigate('lyrics')
}


function resumeLibrary() {
  if (song.value?.file) {
    togglePlayback()
    return
  }

  playAll()
}


function formatNumber(value) {
  return Number(value || 0)
    .toLocaleString()
}
</script>


<template>
  <div
    class="pb-8"
  >
    <!-- HEADER -->
    <section
      class="mb-7
             lg:mb-9"
    >
      <div
        class="flex
               items-end
               justify-between
               gap-5"
      >
        <div>
          <div
            class="flex items-center
                   gap-2
                   text-[#be5c2b]"
          >
            <Sparkles
              class="h-4 w-4"
              :stroke-width="2"
            />

            <p
              class="text-[10px]
                     font-semibold
                     uppercase
                     tracking-[0.2em]"
            >
              Castillo Player
            </p>
          </div>

          <h1
            class="mt-2
                   text-3xl
                   font-semibold
                   tracking-[-0.035em]
                   text-[#252525]
                   sm:text-4xl
                   lg:text-[42px]"
          >
            {{ greeting }}
          </h1>

          <p
            class="mt-2
                   text-sm
                   text-black/40"
          >
            ¿Qué quieres escuchar?
          </p>
        </div>
      </div>
    </section>


    <!-- HERO NOW PLAYING -->
    <section>
      <div
        class="relative
               overflow-hidden
               rounded-[30px]
               bg-[#252525]
               text-white
               shadow-[0_24px_70px_rgba(37,37,37,.16)]
               lg:rounded-[34px]"
      >
        <!-- decorative glow -->
        <div
          class="pointer-events-none
                 absolute
                 -right-16
                 -top-20
                 h-72 w-72
                 rounded-full
                 bg-[#ff6470]/15
                 blur-[70px]"
        />

        <div
          class="pointer-events-none
                 absolute
                 -bottom-28
                 left-1/3
                 h-64 w-64
                 rounded-full
                 bg-[#be5c2b]/15
                 blur-[80px]"
        />


        <div
          class="relative
                 grid gap-6
                 p-5
                 sm:p-6
                 lg:grid-cols-[220px_minmax(0,1fr)]
                 lg:gap-8
                 lg:p-8"
        >
          <!-- COVER -->
          <div
            class="mx-auto
                   w-full
                   max-w-[240px]
                   lg:mx-0
                   lg:max-w-none"
          >
            <div
              class="aspect-square
                     overflow-hidden
                     rounded-[26px]
                     bg-white/10
                     shadow-[0_20px_50px_rgba(0,0,0,.25)]"
            >
              <img
                v-if="song.file"
                :src="coverUrl(song.file)"
                class="h-full
                       w-full
                       object-cover"
              />

              <div
                v-else
                class="flex
                       h-full
                       w-full
                       items-center
                       justify-center"
              >
                <Music2
                  class="h-16 w-16
                         text-white/20"
                  :stroke-width="1.3"
                />
              </div>
            </div>
          </div>


          <!-- CURRENT SONG -->
          <div
            class="flex
                   min-w-0
                   flex-col
                   justify-center"
          >
            <div>
              <p
                class="text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.22em]
                       text-[#ff8b94]"
              >
                Ahora suena
              </p>

              <h2
                class="mt-3
                       line-clamp-2
                       text-2xl
                       font-semibold
                       leading-tight
                       tracking-[-0.03em]
                       sm:text-3xl
                       lg:text-[34px]"
              >
                {{
                  song.title ||
                  'Tu música está lista'
                }}
              </h2>

              <p
                class="mt-2
                       truncate
                       text-sm
                       text-white/55
                       sm:text-base"
              >
                {{
                  song.artist ||
                  'Selecciona una canción para comenzar'
                }}
              </p>

              <p
                v-if="song.album"
                class="mt-1
                       truncate
                       text-xs
                       text-white/35"
              >
                {{ song.album }}
              </p>
            </div>


            <!-- WAVEFORM -->
            <div
              v-if="song.file"
              class="mt-7"
            >
              <WaveformProgress
                class="min-w-0 flex-1"
                :file="song.file || ''"
                :current="elapsed"
                :duration="currentDuration"
                :bars="220"
                :visual-bars="200"
                :height="46"
                active-color="#ff6470"
                inactive-color="#dce0e5"
                @seek="seek"
              />
            </div>


            <!-- ACTIONS -->
            <div
              class="mt-6
                     flex
                     flex-wrap
                     items-center
                     gap-3"
            >
              <button
                class="flex
                       h-12
                       items-center
                       justify-center
                       gap-2
                       rounded-full
                       bg-[#ff6470]
                       px-6
                       text-sm
                       font-semibold
                       text-white
                       shadow-[0_12px_30px_rgba(255,100,112,.25)]
                       transition
                       hover:scale-[1.02]
                       active:scale-[0.98]"
                @click="resumeLibrary"
              >
                <Pause
                  v-if="
                    playing &&
                    song.file
                  "
                  class="h-5 w-5"
                />

                <Play
                  v-else
                  class="ml-0.5
                         h-5 w-5"
                />

                <span>
                  {{
                    song.file
                      ? (
                          playing
                            ? 'Pausar'
                            : 'Continuar'
                        )
                      : 'Reproducir todo'
                  }}
                </span>
              </button>


              <button
                v-if="song.file"
                class="flex
                       h-12
                       items-center
                       gap-2
                       rounded-full
                       bg-white/10
                       px-5
                       text-sm
                       font-medium
                       text-white/75
                       transition
                       hover:bg-white/15
                       hover:text-white"
                @click="openLyrics"
              >
                <Rows3
                  class="h-4 w-4"
                />

                Ver letras
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>


    <!-- QUICK ACCESS -->
    <section
      class="mt-9"
    >
      <div
        class="mb-4
               flex
               items-center
               justify-between"
      >
        <div>
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Explorar
          </p>

          <h2
            class="mt-1
                   text-xl
                   font-semibold
                   tracking-tight"
          >
            Accesos rápidos
          </h2>
        </div>
      </div>


      <div
        class="grid
               grid-cols-2
               gap-3
               xl:grid-cols-4"
      >
        <!-- FAVORITES -->
        <button
          class="group flex flex-col
                 min-h-[120px]
                 rounded-[26px]
                 bg-[#f1eeeb]
                 p-5
                 text-left
                 transition
                 hover:-translate-y-1
                 hover:shadow-lg"
          @click="
            navigate('favorites')
          "
        >
          <div
            class="flex
                   h-10 w-10
                   items-center
                   justify-center
                   rounded-2xl
                   bg-[#ff6470]/10
                   text-[#ff6470]"
          >
            <Heart
              class="h-5 w-5"
            />
          </div>

          <p
            class="mt-5
                   text-sm
                   font-semibold"
          >
            Favoritos
          </p>

          <p
            class="mt-1
                   text-[11px]
                   text-black/35"
          >
            Tus canciones guardadas
          </p>
        </button>


        <!-- HASHTAGS -->
        <button
          class="group flex flex-col
                 min-h-[120px]
                 rounded-[26px]
                 bg-[#f1eeeb]
                 p-5
                 text-left
                 transition
                 hover:-translate-y-1
                 hover:shadow-lg"
          @click="
            navigate('hashtags')
          "
        >
          <div
            class="flex
                   h-10 w-10
                   items-center
                   justify-center
                   rounded-2xl
                   bg-[#be5c2b]/10
                   text-[#be5c2b]"
          >
            <Hash
              class="h-5 w-5"
            />
          </div>

          <p
            class="mt-5
                   text-sm
                   font-semibold"
          >
            Hashtags
          </p>

          <p
            class="mt-1
                   text-[11px]
                   text-black/35"
          >
            Organiza tu música
          </p>
        </button>


        <!-- QUEUE -->
        <button
          class="group flex flex-col
                 min-h-[120px]
                 rounded-[26px]
                 bg-[#f1eeeb]
                 p-5
                 text-left
                 transition
                 hover:-translate-y-1
                 hover:shadow-lg"
          @click="
            navigate('queue')
          "
        >
          <div
            class="flex
                   h-10 w-10
                   items-center
                   justify-center
                   rounded-2xl
                   bg-black/[0.05]
                   text-black/55"
          >
            <Rows3
              class="h-5 w-5"
            />
          </div>

          <p
            class="mt-5
                   text-sm
                   font-semibold"
          >
            Cola
          </p>

          <p
            class="mt-1
                   text-[11px]
                   text-black/35"
          >
            Mira qué sigue
          </p>
        </button>


        <!-- LIBRARY -->
        <button
          class="group flex flex-col
                 min-h-[120px]
                 rounded-[26px]
                 bg-[#f4f4cb]
                 p-5
                 text-left
                 transition
                 hover:-translate-y-1
                 hover:shadow-lg"
          @click="
            navigate('library')
          "
        >
          <div
            class="flex
                   h-10 w-10
                   items-center
                   justify-center
                   rounded-2xl
                   bg-black/[0.06]
                   text-black/60"
          >
            <LibraryBig
              class="h-5 w-5"
            />
          </div>

          <p
            class="mt-5
                   text-sm
                   font-semibold"
          >
            Biblioteca
          </p>

          <p
            class="mt-1
                   text-[11px]
                   text-black/40"
          >
            Toda tu música
          </p>
        </button>
      </div>
    </section>

    <!-- RECENTLY PLAYED -->
    <section
      v-if="recentSongs.length"
      class="mt-10"
    >
      <div
        class="mb-5
               flex
               items-end
               justify-between"
      >
        <div>
          <div
            class="flex
                   items-center
                   gap-2
                   text-[#be5c2b]"
          >
            <Clock3
              class="h-4 w-4"
            />

            <p
              class="text-[9px]
                     font-semibold
                     uppercase
                     tracking-[0.18em]"
            >
              Historial
            </p>
          </div>

          <h2
            class="mt-1
                   text-2xl
                   font-semibold
                   tracking-tight"
          >
            Reproducido recientemente
          </h2>
        </div>
      </div>


      <div
        class="-mx-5
               flex
               gap-4
               overflow-x-auto
               px-5
               pb-3
               sm:-mx-7
               sm:px-7
               lg:mx-0
               lg:grid
               lg:grid-cols-3
               lg:overflow-visible
               lg:px-0
               2xl:grid-cols-6"
      >
        <button
          v-for="item in recentSongs"
          :key="item.file"

          class="group
                 w-[150px]
                 shrink-0
                 text-left
                 sm:w-[170px]
                 lg:w-auto"

          @click="
            playFile(
              item.file
            )
          "
        >
          <div
            class="relative
                   aspect-square
                   overflow-hidden
                   rounded-[24px]
                   bg-[#eeeae6]"
          >
            <img
              :src="
                coverUrl(
                  item.file
                )
              "
              class="h-full
                     w-full
                     object-cover
                     transition
                     duration-500
                     group-hover:scale-[1.04]"
            />


            <div
              class="absolute
                     inset-0
                     bg-gradient-to-t
                     from-black/35
                     via-transparent
                     to-transparent
                     opacity-0
                     transition
                     group-hover:opacity-100"
            />


            <div
              class="absolute
                     bottom-3
                     right-3
                     flex
                     h-10 w-10
                     translate-y-2
                     items-center
                     justify-center
                     rounded-full
                     bg-[#ff6470]
                     text-white
                     opacity-0
                     shadow-lg
                     transition
                     group-hover:translate-y-0
                     group-hover:opacity-100"
            >
              <Play
                class="ml-0.5
                       h-4 w-4"
                fill="currentColor"
              />
            </div>
          </div>


          <p
            class="mt-3
                   truncate
                   text-sm
                   font-semibold"
          >
            {{
              item.title ||
              'Sin título'
            }}
          </p>

          <p
            class="mt-1
                   truncate
                   text-xs
                   text-black/40"
          >
            {{
              item.artist ||
              '—'
            }}
          </p>
        </button>
      </div>
    </section>

    <!-- LIBRARY STATS -->
    <section
      class="mt-10"
    >
      <div
        class="rounded-[30px]
               border
               border-black/[0.04]
               bg-white
               p-5
               shadow-[0_12px_40px_rgba(0,0,0,.035)]
               sm:p-6"
      >
        <div
          class="flex
                 items-center
                 gap-3"
        >
          <div
            class="flex
                   h-10 w-10
                   items-center
                   justify-center
                   rounded-2xl
                   bg-[#be5c2b]/10
                   text-[#be5c2b]"
          >
            <Disc3
              class="h-5 w-5"
            />
          </div>

          <div>
            <p
              class="text-[9px]
                     font-semibold
                     uppercase
                     tracking-[0.18em]
                     text-black/30"
            >
              Tu colección
            </p>

            <h2
              class="mt-0.5
                     text-lg
                     font-semibold"
            >
              Biblioteca
            </h2>
          </div>
        </div>


        <div
          class="mt-6
                 grid
                 grid-cols-3
                 divide-x
                 divide-black/[0.06]"
        >
          <button
            class="px-2
                   text-center"
            @click="
              navigate('library')
            "
          >
            <div
              class="text-xl
                     font-semibold
                     tabular-nums
                     sm:text-3xl"
            >
              {{
                formatNumber(
                  totalSongs
                )
              }}
            </div>

            <div
              class="mt-1
                     text-[10px]
                     text-black/35
                     sm:text-xs"
            >
              Canciones
            </div>
          </button>


          <button
            class="px-2
                   text-center"
            @click="
              navigate('albums')
            "
          >
            <div
              class="text-xl
                     font-semibold
                     tabular-nums
                     sm:text-3xl"
            >
              {{
                formatNumber(
                  totalAlbums
                )
              }}
            </div>

            <div
              class="mt-1
                     text-[10px]
                     text-black/35
                     sm:text-xs"
            >
              Álbumes
            </div>
          </button>


          <button
            class="px-2
                   text-center"
            @click="
              navigate('artists')
            "
          >
            <div
              class="text-xl
                     font-semibold
                     tabular-nums
                     sm:text-3xl"
            >
              {{
                formatNumber(
                  totalArtists
                )
              }}
            </div>

            <div
              class="mt-1
                     text-[10px]
                     text-black/35
                     sm:text-xs"
            >
              Artistas
            </div>
          </button>
        </div>
      </div>
    </section>


    <!-- ALBUMS -->
    <section
      class="mt-11"
    >
      <div
        class="mb-5
               flex
               items-end
               justify-between
               gap-4"
      >
        <div>
          <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.18em]
                   text-[#be5c2b]"
          >
            Descubrir
          </p>

          <h2
            class="mt-1
                   text-2xl
                   font-semibold
                   tracking-tight"
          >
            Álbumes
          </h2>
        </div>

        <button
          class="flex
                 items-center
                 gap-1
                 text-xs
                 font-medium
                 text-black/40
                 transition
                 hover:text-[#be5c2b]"
          @click="
            navigate('albums')
          "
        >
          Ver todos

          <ArrowRight
            class="h-4 w-4"
          />
        </button>
      </div>


      <!-- MOBILE HORIZONTAL / DESKTOP GRID -->
      <div
        class="-mx-5
               flex
               gap-4
               overflow-x-auto
               px-5
               pb-3
               sm:-mx-7
               sm:px-7
               lg:mx-0
               lg:grid
               lg:grid-cols-3
               lg:overflow-visible
               lg:px-0
               xl:grid-cols-4
               2xl:grid-cols-6"
      >
        <button
          v-for="album in featuredAlbums"
          :key="
            `${album.artist}-${album.album}`
          "
          class="group
                 w-[155px]
                 shrink-0
                 text-left
                 sm:w-[175px]
                 lg:w-auto"
          @dblclick="
            album.file &&
            playFile(album.file)
          "
        >
          <div
            class="relative
                   aspect-square
                   overflow-hidden
                   rounded-[24px]
                   bg-[#eeeae6]"
          >
            <img
              v-if="album.file"
              :src="
                coverUrl(
                  album.file
                )
              "
              class="h-full
                     w-full
                     object-cover
                     transition
                     duration-500
                     group-hover:scale-[1.04]"
            />

            <div
              v-else
              class="flex
                     h-full
                     w-full
                     items-center
                     justify-center"
            >
              <Album
                class="h-9 w-9
                       text-black/15"
              />
            </div>


            <div
              class="absolute
                     inset-0
                     bg-gradient-to-t
                     from-black/20
                     via-transparent
                     to-transparent
                     opacity-0
                     transition
                     group-hover:opacity-100"
            />

            <div
              class="absolute
                     bottom-3
                     right-3
                     flex
                     h-10 w-10
                     translate-y-2
                     items-center
                     justify-center
                     rounded-full
                     bg-[#ff6470]
                     text-white
                     opacity-0
                     shadow-lg
                     transition
                     group-hover:translate-y-0
                     group-hover:opacity-100"
            >
              <Play
                class="ml-0.5
                       h-4 w-4"
                fill="currentColor"
              />
            </div>
          </div>


          <p
            class="mt-3
                   truncate
                   text-sm
                   font-semibold"
          >
            {{
              album.album ||
              'Sin álbum'
            }}
          </p>

          <p
            class="mt-1
                   truncate
                   text-xs
                   text-black/40"
          >
            {{
              album.artist ||
              '—'
            }}
          </p>
        </button>
      </div>
    </section>
  </div>
</template>