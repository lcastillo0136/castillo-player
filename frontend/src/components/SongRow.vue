<script setup>
import {
  computed,
  ref
} from 'vue'

import {
  AudioLines,
  Ellipsis,
  Heart,
  Hash,
  ListMusic,
  Play,
  Plus,
  Trash2,
  Tags
} from 'lucide-vue-next'

import {
  useCastilloApi
} from '../composables/useCastilloApi'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

import SongHashtagsDialog
  from './SongHashtagsDialog.vue'

const props = defineProps({
  song: {
    type: Object,
    required: true
  },

  removableFrom: {
    type: String,
    default: ''
  }
})

const {
  song: currentSong,
  playing,

  coverUrl,

  playFile,
  queueAdd,

  toggleFavorite,
  isFavorite,

  removeFromPlaylist
} = useCastilloApi()

const {
  navigate
} = useCastilloNavigation()

const menuOpen = ref(false)
const hashtagDialogOpen = ref(false)
const hiddenAfterHashtagEdit = ref(false)
const isCurrentTrack = computed(() => {
  return (
    currentSong.value?.file ===
    props.song.file
  )
})


const isCurrentTrackPlaying = computed(() => {
  return (
    isCurrentTrack.value &&
    playing.value
  )
})

function formatTime(value) {
  const seconds = Math.floor(
    Number(value) || 0
  )

  if (!seconds) {
    return '—'
  }

  return (
    `${Math.floor(seconds / 60)}:` +
    String(seconds % 60).padStart(2, '0')
  )
}

function editTags() {
  const file =
    props.song?.file

  if (!file) {
    return
  }
  
  menuOpen.value = false

  navigate(
    'tags-edit',
    {
      file
    }
  )
}

function manageHashtags() {
  menuOpen.value = false
  hashtagDialogOpen.value = true
}


function handleHashtagsSaved(payload = {}) {
  const currentHashtag =
    String(
      props.song?.hashtag || ''
    ).trim()

  const currentSource =
    props.song?.hashtag_source || ''

  const embedded =
    Array.isArray(payload.hashtags)
      ? payload.hashtags
      : []

  /*
   * Si esta fila pertenece al detalle de un hashtag Castillo
   * y acabamos de quitar ese hashtag, la ocultamos inmediatamente.
   */
  if (
    currentHashtag &&
    currentSource === 'embedded' &&
    !embedded.includes(currentHashtag)
  ) {
    hiddenAfterHashtagEdit.value = true
  }

  window.dispatchEvent(
    new CustomEvent(
      'castillo:hashtags-updated',
      {
        detail: {
          file:
            props.song?.file || '',
          hashtags:
            embedded
        }
      }
    )
  )
}
</script>

<template>
  <div
    v-if="!hiddenAfterHashtagEdit"
    class="group relative grid
           grid-cols-[44px_minmax(0,1fr)_auto]
           items-center gap-3
           rounded-2xl px-2 py-2
           transition
           hover:bg-black/[0.035]
           sm:grid-cols-[48px_minmax(0,1.7fr)_minmax(100px,.8fr)_72px_auto]"
    :class="
      isCurrentTrack
        ? 'bg-[#fff1e9] ring-1 ring-[#ff6470]/10'
        : ''
    "
  >
    <button
      class="relative h-11 w-11
             overflow-hidden rounded-xl
             bg-[#ece8e4]"
      @click="playFile(song.file)"
    >
      <img
        :src="coverUrl(song.file)"
        class="h-full w-full object-cover"
      />


      <!-- CANCIÓN ACTUAL -->
      <span
        v-if="isCurrentTrack"
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


      <!-- HOVER NORMAL -->
      <span
        v-else
        class="absolute inset-0
               flex items-center justify-center
               bg-black/30
               opacity-0 transition
               group-hover:opacity-100"
      >
        <Play
          class="h-5 w-5 text-white"
          :stroke-width="2.2"
        />
      </span>
    </button>

    <div class="min-w-0">
      <p
        class="truncate text-sm font-medium"
      >
        {{
          song.title ||
          song.file.split('/').pop()
        }}
      </p>

      <p
        class="mt-0.5 truncate
               text-xs text-black/40
               sm:hidden"
      >
        {{ song.artist || '—' }}
      </p>

      <span
        v-if="song.hashtag_source"
        class="mt-1 inline-flex
               items-center rounded-full
               px-2 py-0.5
               text-[9px] font-semibold"
        :class="
          song.hashtag_source === 'embedded'
            ? 'bg-[#f2f478]/70 text-[#252525]'
            : 'bg-black/[0.05] text-black/35'
        "
      >
        {{
          song.hashtag_source === 'embedded'
            ? 'Castillo'
            : 'Heredado'
        }}
      </span>
    </div>

    <p
      class="hidden truncate
             text-xs text-black/45
             sm:block"
    >
      {{ song.artist || '—' }}
    </p>

    <p
      class="hidden text-right
             text-xs tabular-nums
             text-black/30
             sm:block"
    >
      {{
        formatTime(
          song.duration || song.time
        )
      }}
    </p>

    <div
      class="flex items-center
             justify-end gap-1"
    >
      <button
        class="rounded-full p-2
               hover:bg-black/5"
        title="Favorito"
        @click="
          toggleFavorite(song.file)
        "
      >
        <Heart
          class="h-5 w-5"
          :class="
            isFavorite(song.file)
              ? 'text-[#be5c2b]'
              : 'text-black/35'
          "
          :fill="
            isFavorite(song.file)
              ? 'currentColor'
              : 'none'
          "
          :stroke-width="2"
        />
      </button>

      <button
        class="hidden rounded-full p-2
               hover:bg-black/5 sm:block"
        title="Agregar a cola"
        @click="queueAdd(song.file)"
      >
        <Plus
          class="h-5 w-5 text-black/40"
          :stroke-width="2"
        />
      </button>

      <button
        v-if="removableFrom"
        class="rounded-full p-2
               hover:bg-red-50"
        title="Quitar de playlist"
        @click="
          removeFromPlaylist(
            removableFrom,
            song.file
          )
        "
      >
        <Trash2
          class="h-5 w-5 text-black/30"
          :stroke-width="2"
        />
      </button>

      <div class="relative">
        <button
          class="rounded-full p-2
                 hover:bg-black/5"
          @click="
            menuOpen = !menuOpen
          "
        >
          <Ellipsis
            class="h-5 w-5 text-black/40"
            :stroke-width="2"
          />
        </button>

        <div
          v-if="menuOpen"
          class="absolute right-0 top-10
                 z-30 w-56
                 overflow-hidden
                 rounded-2xl
                 border border-black/5
                 bg-white p-2
                 shadow-xl"
        >
          <button
            class="flex w-full items-center
                   gap-3 rounded-xl
                   px-3 py-2.5
                   text-left text-xs
                   hover:bg-black/5"
            @click="
              playFile(song.file);
              menuOpen = false
            "
          >
            <Play class="h-4 w-4" :stroke-width="2"/>
            Reproducir ahora
          </button>

          <button
            class="flex w-full items-center
                   gap-3 rounded-xl
                   px-3 py-2.5
                   text-left text-xs
                   hover:bg-black/5"
            @click="
              queueAdd(song.file);
              menuOpen = false
            "
          >
            <ListMusic class="h-4 w-4" :stroke-width="2"/>
            Agregar a cola
          </button>

          <button
            class="flex w-full items-center
                   gap-3 rounded-xl
                   px-3 py-2.5
                   text-left text-xs
                   transition
                   hover:bg-black/5"
            @click="manageHashtags"
          >
            <Hash
              class="h-4 w-4
                     text-[#be5c2b]"
            />

            <span>
              Administrar hashtags
            </span>
          </button>

          <button
            class="flex
                   w-full
                   items-center
                   gap-3
                   rounded-xl
                   px-4 py-2.5
                   text-left
                   text-xs
                   transition
                   hover:bg-black/[0.04]"
            @click="editTags"
          >
            <Tags
              class="h-4 w-4
                     text-black/45"
            />

            <span>
              Editar tags
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <SongHashtagsDialog
    :open="hashtagDialogOpen"
    :song="song"
    @close="
      hashtagDialogOpen = false
    "
    @saved="handleHashtagsSaved"
  />
</template>
