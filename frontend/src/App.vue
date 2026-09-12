<script setup>
import {
  nextTick,
  watch,
  onBeforeUnmount,
  onMounted,
  ref
} from 'vue'

import Sidebar
  from './components/Sidebar.vue'

import NowPlayingPanel
  from './components/NowPlayingPanel.vue'

import BottomPlayer
  from './components/BottomPlayer.vue'

import MobileNav
  from './components/MobileNav.vue'

import MobileMiniPlayer
  from './components/MobileMiniPlayer.vue'

import HomeView
  from './views/HomeView.vue'

import LibraryView
  from './views/LibraryView.vue'

import SearchView
  from './views/SearchView.vue'

import FavoritesView
  from './views/FavoritesView.vue'

import PlaylistsView
  from './views/PlaylistsView.vue'

import HashtagsView
  from './views/HashtagsView.vue'

import QueueView
  from './views/QueueView.vue'

import MobileNowPlaying
  from './views/MobileNowPlaying.vue'

import LyricsView
  from './views/LyricsView.vue'

import LyricsEditorView
  from './views/LyricsEditorView.vue'

import PendingChangesView
  from './views/PendingChangesView.vue'

import ConnectionLock
  from './components/ConnectionLock.vue'

import NasSyncGlobalStatus
  from './components/NasSyncGlobalStatus.vue'

import TagEditorView
  from './views/TagEditorView.vue'

import SettingsView
  from './views/SettingsView.vue'

import {
  useCastilloApi
} from './composables/useCastilloApi'

import {
  useCastilloNavigation
} from './composables/useCastilloNavigation'

import {
  useListeningHistory
} from './composables/useListeningHistory'

const {
  currentView,
  startNavigation,
  stopNavigation,
  navigate
} = useCastilloNavigation()

const {
  startPolling,
  stopPolling,

  loadLibrary,
  loadQueue,
  loadFavorites,
  loadPlaylists,
  loadHashtags
} = useCastilloApi()

useListeningHistory()

onMounted(async () => {
  startNavigation()
  
  startPolling()

  await Promise.all([
    loadLibrary(),
    loadQueue(),
    loadFavorites(),
    loadPlaylists(),
    loadHashtags().catch(() => [])
  ])
})

onBeforeUnmount(() => {
  stopNavigation()
  stopPolling()
})

watch(
  currentView,

  async view => {
    if (
      view !== 'player'
    ) {
      return
    }

    await nextTick()

    const main =
      document.getElementById(
        'castillo-main-scroll'
      )

    if (!main) {
      return
    }

    main.scrollTo({
      top: 0,
      left: 0,
      behavior: 'auto'
    })
  },

  {
    immediate: true,
    flush: 'post'
  }
)
</script>

<template>
  <div
    class="h-screen overflow-hidden
           bg-[#faf9f7]
           text-[#252525]"
  >
    <NasSyncGlobalStatus />

    <div class="flex h-full
             overflow-hidden">

      <Sidebar
        :current-view="currentView"
        @navigate="navigate"
      />

      <main
        id="castillo-main-scroll"
        class="castillo-scroll
               min-w-0 flex-1
               overflow-y-auto
               overflow-x-hidden
               lg:h-[calc(100vh-112px)]
               lg:pb-8"
        :class="
          currentView === 'player' ||
          currentView === 'lyrics'
            ? 'pb-0'
            : 'pb-[170px]'
        "
      >
        <!-- MOBILE HEADER -->
        <header
          v-if="
            currentView !== 'player'
          "
          class="flex h-16
                 items-center
                 justify-between
                 px-5 lg:hidden"
        >
          <div>
            <div
              class="text-[9px]
                     font-semibold
                     uppercase
                     tracking-[0.28em]
                     text-[#be5c2b]"
            >
              Castillo
            </div>

            <div
              class="text-lg font-semibold"
            >
              Player
            </div>
          </div>
        </header>

        <div
          class="mx-auto
                 max-w-[1500px]"
          :class="
            currentView === 'player'
              ? 'px-5 pb-4 pt-0 sm:px-7'
              : 'px-5 py-4 sm:px-7 lg:px-9 lg:py-8'
          "
        >
          <HomeView
            v-if="
              currentView === 'home'
            "
          />

          <MobileNowPlaying
            v-else-if="
              currentView === 'player'
            "
          />

          <LyricsEditorView
            v-else-if="
              currentView === 'lyrics-edit'
            "
          />

          <TagEditorView
            v-else-if="
              currentView === 'tags-edit'
            "
          />

          <LyricsView
            v-else-if="
              currentView === 'lyrics'
            "
          />

          <SearchView
            v-else-if="
              currentView === 'search'
            "
          />

          <LibraryView
            v-else-if="
              currentView === 'library'
            "
            mode="songs"
          />

          <LibraryView
            v-else-if="
              currentView === 'albums'
            "
            mode="albums"
          />

          <LibraryView
            v-else-if="
              currentView === 'artists'
            "
            mode="artists"
          />
          
          <QueueView
            v-else-if="
              currentView === 'queue'
            "
          />

          <FavoritesView
            v-else-if="
              currentView === 'favorites'
            "
          />

          <HashtagsView
            v-else-if="
              currentView === 'hashtags'
            "
          />

          <PlaylistsView
            v-else-if="
              currentView === 'playlists'
            "
          />
          <PendingChangesView
            v-else-if="
              currentView === 'pending'
            "
          />
          <SettingsView
            v-else-if="
              currentView === 'settings'
            "
          />
        </div>
      </main>

      <NowPlayingPanel />
    </div>

    <BottomPlayer />

    <!-- MINI PLAYER MOBILE -->
    <MobileMiniPlayer
      v-if="
        currentView !== 'player' &&
        currentView !== 'lyrics'
      "
    />    

    <MobileNav
      :current-view="currentView"
      @navigate="navigate"
    />
    
    <!-- GLOBAL CONNECTION LOCK -->
    <ConnectionLock />
  </div>
</template>