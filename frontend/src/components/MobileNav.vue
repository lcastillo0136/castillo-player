<script setup>
import {
  Hash,
  Heart,
  House,
  ListMusic,
  Music2,
  Search
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
    label: 'Biblioteca',
    icon: Music2
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
  }
]
</script>

<template>
  <nav
    v-if="currentView !== 'player'"
    class="fixed inset-x-0 bottom-0
           z-50 flex h-16
           items-center justify-around
           border-t border-black/5
           bg-white/95
           backdrop-blur-xl
           lg:hidden"
  >
    <button
      v-for="item in items"
      :key="item.id"
      class="flex min-w-[58px]
             flex-col items-center
             justify-center gap-1"
      :class="
        currentView === item.id
          ? 'text-[#ff6470]'
          : 'text-black/35'
      "
      @click="
        emit('navigate', item.id)
      "
    >
      <component
        :is="item.icon"
        class="h-5 w-5"
      />

      <span class="text-[9px]">
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>