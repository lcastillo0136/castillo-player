<script setup>
import {
  computed,
  onMounted,
  ref,
  watch
} from 'vue'

import {
  ArrowLeft,
  Check,
  FileAudio,
  FileText,
  Image,
  Loader2,
  RefreshCw,
  Tags
} from 'lucide-vue-next'

import {
  useCastilloNavigation
} from '../composables/useCastilloNavigation'

import {
  useNasSync
} from '../composables/useNasSync'

const {
  goBack
} = useCastilloNavigation()

const loading = ref(false)
const error = ref('')

const items = ref([])

const selected = ref(
  new Set()
)

const checking = ref(false)
const checkResult = ref(null)

const {
  syncing,
  syncResult,
  syncFiles
} = useNasSync()

async function loadPending() {
  loading.value = true
  error.value = ''


  try {
    const response =
      await fetch(
        '/castillo-api/pending.php',
        {
          cache: 'no-store'
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible cargar los cambios pendientes.'
      )
    }


    items.value =
      data.items || []


    /*
     * Por ahora seleccionamos todos
     * automáticamente.
     */
    selected.value =
      new Set(
        items.value.map(
          item =>
            item.relative_path
        )
      )

    checkResult.value = null

  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible cargar los cambios pendientes.'

  } finally {
    loading.value = false
  }
}

async function checkSelected() {
  if (
    !selected.value.size ||
    checking.value
  ) {
    return
  }


  checking.value = true
  error.value = ''
  checkResult.value = null


  try {
    const response =
      await fetch(
        '/castillo-api/pending.php',
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify({
              action:
                'check',

              files:
                Array.from(
                  selected.value
                )
            })
        }
      )


    const data =
      await response.json()


    if (!response.ok) {
      throw new Error(
        data.error ||
        'No fue posible revisar la selección.'
      )
    }


    checkResult.value =
      data


  } catch (exception) {
    error.value =
      exception.message ||
      'No fue posible revisar la selección.'

  } finally {
    checking.value = false
  }
}

async function syncSelected() {
  if (
    !checkResult.value?.can_sync ||
    !selected.value.size ||
    syncing.value
  ) {
    return
  }


  const files =
    Array.from(
      selected.value
    )


  const confirmed =
    window.confirm(
      'Se copiarán los archivos seleccionados del USB al NAS. ¿Deseas continuar?'
    )


  if (!confirmed) {
    return
  }


  error.value = ''


  try {
    await syncFiles(
      files
    )


  } catch (exception) {
    /*
     * Si el backend detectó que el NAS
     * cambió justo antes de escribir,
     * recuperamos los estados.
     */
    const data =
      exception?.data


    if (
      Array.isArray(
        data?.items
      )
    ) {
      checkResult.value = {
        ...data,

        ready:
          data.items.filter(
            item =>
              item.status ===
              'ready'
          ).length,

        conflicts:
          data.items.filter(
            item =>
              item.status ===
              'conflict'
          ).length,

        blocked:
          data.items.filter(
            item =>
              item.status ===
              'blocked'
          ).length,

        can_sync:
          false
      }
    }


    error.value =
      exception?.message ||
      'No fue posible sincronizar la selección.'
  }
}

function toggleItem(
  relativePath
) {
  const next =
    new Set(
      selected.value
    )


  if (
    next.has(
      relativePath
    )
  ) {
    next.delete(
      relativePath
    )

  } else {
    next.add(
      relativePath
    )
  }


  selected.value = next
  checkResult.value = null
}

function toggleAll() {
  if (
    selected.value.size ===
    items.value.length
  ) {
    selected.value =
      new Set()

  } else {
    selected.value =
      new Set(
        items.value.map(
          item =>
            item.relative_path
        )
      )
  }
  checkResult.value = null
}

function typeLabel(type) {
  switch (type) {
    case 'tags':
      return 'Tags'

    case 'artwork':
      return 'Portada'

    case 'lyrics':
      return 'Letras'

    default:
      return type
  }
}

function baselineLabel(item) {
  const status =
    item.nas_baseline?.status


  if (status === 'present') {
    return 'Existe en NAS'
  }


  if (status === 'missing') {
    return 'Nuevo para NAS'
  }


  return 'NAS no disponible'
}

function checkFor(
  relativePath
) {
  return (
    checkResult.value
      ?.items
      ?.find(
        item =>
          item.relative_path ===
          relativePath
      )
    || null
  )
}

function checkLabel(item) {
  if (!item) {
    return ''
  }


  if (item.status === 'ready') {
    return 'Listo'
  }


  if (item.status === 'conflict') {
    return 'Conflicto'
  }


  if (item.status === 'blocked') {
    return 'Bloqueado'
  }


  return item.status || ''
}

function checkClass(item) {
  if (!item) {
    return ''
  }


  if (item.status === 'ready') {
    return (
      'bg-emerald-50 ' +
      'text-emerald-700'
    )
  }


  if (item.status === 'conflict') {
    return (
      'bg-amber-50 ' +
      'text-amber-700'
    )
  }


  if (item.status === 'blocked') {
    return (
      'bg-red-50 ' +
      'text-red-600'
    )
  }


  return (
    'bg-black/[0.04] ' +
    'text-black/40'
  )
}

const selectedCount = computed(
  () =>
    selected.value.size
)

const allSelected = computed(
  () =>
    Boolean(
      items.value.length
    ) &&
    selected.value.size ===
      items.value.length
)

watch(
  syncing,
  async (
    value,
    previous
  ) => {
    /*
     * La sincronización acaba de terminar.
     *
     * Si seguimos en esta pantalla,
     * recargamos inmediatamente los
     * pendientes.
     */
    if (
      previous === true &&
      value === false
    ) {
      await loadPending()
    }
  }
)

onMounted(
  loadPending
)
</script>


<template>
  <div
    class="mx-auto
           min-h-full
           max-w-5xl
           pb-16"
  >
    <!-- HEADER -->
    <header
      class="flex
             items-center
             justify-between
             border-b
             border-black/5
             py-5"
    >
      <button
        class="flex
               items-center
               gap-2
               rounded-full
               px-3 py-2
               text-sm
               text-black/45
               hover:bg-black/[0.04]"
        @click="goBack"
      >
        <ArrowLeft
          class="h-4 w-4"
        />

        Volver
      </button>


      <div class="text-center">
        <p
          class="text-[9px]
                 font-semibold
                 uppercase
                 tracking-[0.18em]
                 text-[#be5c2b]"
        >
          Castillo
        </p>

        <h1
          class="mt-1
                 text-base
                 font-semibold"
        >
          Cambios pendientes
        </h1>
      </div>


      <button
        class="flex
               h-10 w-10
               items-center
               justify-center
               rounded-full
               bg-black/[0.04]
               text-black/45
               hover:bg-black/[0.07]"
        @click="loadPending"
      >
        <RefreshCw
          class="h-4 w-4"
          :class="
            loading
              ? 'animate-spin'
              : ''
          "
        />
      </button>
    </header>


    <!-- SUMMARY -->
    <section
      class="mt-8
             rounded-[28px]
             bg-[#252525]
             p-6
             text-white"
    >
      <p
        class="text-[10px]
               uppercase
               tracking-[0.16em]
               text-white/40"
      >
        Cambios locales
      </p>

      <div
        class="mt-3
               flex
               items-end
               justify-between
               gap-4"
      >
        <div>
          <p
            class="text-3xl
                   font-semibold"
          >
            {{ items.length }}
          </p>

          <p
            class="mt-1
                   text-xs
                   text-white/45"
          >
            archivos pendientes
          </p>
        </div>


        <div
          class="text-right"
        >
          <p
            class="text-xl
                   font-semibold
                   text-[#f2f478]"
          >
            {{ selectedCount }}
          </p>

          <p
            class="mt-1
                   text-xs
                   text-white/45"
          >
            seleccionados
          </p>
        </div>
      </div>
    </section>


    <!-- ERROR -->
    <div
      v-if="error"
      class="mt-6
             rounded-[20px]
             bg-red-50
             px-4 py-3
             text-sm
             text-red-600"
    >
      {{ error }}
    </div>

		<div
		  v-if="
		    syncResult?.ok
		  "
		  class="mt-6
		         rounded-[20px]
		         bg-emerald-50
		         px-5 py-4"
		>
		  <p
		    class="text-sm
		           font-semibold
		           text-emerald-700"
		  >
		    Sincronización completada
		  </p>

		  <p
		    class="mt-1
		           text-xs
		           text-emerald-700/70"
		  >
		    {{
		      syncResult.synced
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
		    con el NAS.
		  </p>
		</div>

    <!-- LOADING -->
    <div
      v-if="loading"
      class="flex
             min-h-[280px]
             items-center
             justify-center"
    >
      <Loader2
        class="h-6 w-6
               animate-spin
               text-black/25"
      />
    </div>


    <!-- EMPTY -->
    <div
      v-else-if="
        !items.length
      "
      class="mt-8
             rounded-[28px]
             bg-[#f1eeeb]
             px-6 py-16
             text-center"
    >
      <Check
        class="mx-auto
               h-10 w-10
               text-[#be5c2b]"
      />

      <p
        class="mt-4
               font-semibold"
      >
        Todo sincronizado
      </p>

      <p
        class="mt-1
               text-sm
               text-black/35"
      >
        No hay cambios locales pendientes.
      </p>
    </div>


    <!-- LIST -->
    <section
      v-else
      class="mt-8"
    >
      <div
        class="mb-3
               flex
               items-center
               justify-between"
      >
        <p
          class="text-xs
                 font-semibold
                 text-black/40"
        >
          Archivos
        </p>


        <button
          class="text-xs
                 font-semibold
                 text-[#be5c2b]"
          @click="toggleAll"
        >
          {{
            allSelected
              ? 'Deseleccionar todos'
              : 'Seleccionar todos'
          }}
        </button>
      </div>


      <div
        class="space-y-3"
      >
        <article
          v-for="item in items"
          :key="
            item.relative_path
          "
          class="flex
                 items-center
                 gap-4
                 rounded-[22px]
                 border
                 border-black/[0.04]
                 bg-[#f6f3f0]
                 p-4"
        >
          <!-- CHECK -->
          <button
            class="flex
                   h-8 w-8
                   shrink-0
                   items-center
                   justify-center
                   rounded-full
                   border
                   transition"
            :class="
              selected.has(
                item.relative_path
              )
                ? 'border-[#252525] bg-[#252525] text-[#f2f478]'
                : 'border-black/10 bg-white text-transparent'
            "
            @click="
              toggleItem(
                item.relative_path
              )
            "
          >
            <Check
              class="h-4 w-4"
            />
          </button>


          <!-- FILE ICON -->
          <div
            class="flex
                   h-11 w-11
                   shrink-0
                   items-center
                   justify-center
                   rounded-[14px]
                   bg-white"
          >
            <FileText
              v-if="
                item.relative_path
                  .toLowerCase()
                  .endsWith('.lrc')
              "
              class="h-5 w-5
                     text-[#be5c2b]"
            />

            <FileAudio
              v-else
              class="h-5 w-5
                     text-[#625d5a]"
            />
          </div>


          <!-- INFO -->
          <div
            class="min-w-0
                   flex-1"
          >
            <p
              class="truncate
                     text-sm
                     font-semibold"
            >
              {{
                item.relative_path
                  .split('/')
                  .pop()
              }}
            </p>


            <p
              class="mt-1
                     truncate
                     text-[11px]
                     text-black/30"
            >
              {{ item.relative_path }}
            </p>


            <div
              class="mt-2
                     flex
                     flex-wrap
                     gap-1.5"
            >
              <span
                v-for="
                  type in item.types
                "
                :key="type"
                class="inline-flex
                       items-center
                       gap-1
                       rounded-full
                       bg-white
                       px-2.5 py-1
                       text-[10px]
                       font-medium
                       text-black/45"
              >
                <Tags
                  v-if="
                    type === 'tags'
                  "
                  class="h-3 w-3"
                />

                <Image
                  v-else-if="
                    type === 'artwork'
                  "
                  class="h-3 w-3"
                />

                <FileText
                  v-else
                  class="h-3 w-3"
                />

                {{
                  typeLabel(type)
                }}
              </span>
            </div>
            <div
						  v-if="
						    checkFor(
						      item.relative_path
						    )
						  "
						  class="flex mt-2 gap-2"
						>
						  <span
						    class="inline-flex
						           rounded-full
						           px-2.5 py-1
						           text-[10px]
						           font-semibold"
						    :class="
						      checkClass(
						        checkFor(
						          item.relative_path
						        )
						      )
						    "
						  >
						    {{
						      checkLabel(
						        checkFor(
						          item.relative_path
						        )
						      )
						    }}
						  </span>


						  <p
						    class="mt-1
						           text-[10px]
						           text-black/35"
						  >
						    {{
						      checkFor(
						        item.relative_path
						      )?.message
						    }}
						  </p>
						</div>
          </div>


          <!-- NAS -->
          <div
            class="hidden
                   shrink-0
                   text-right
                   sm:block"
          >
            <p
              class="text-[10px]
                     font-semibold"
              :class="
                item.nas_baseline
                  ?.status === 'missing'
                  ? 'text-[#be5c2b]'
                  : 'text-black/35'
              "
            >
              {{
                baselineLabel(
                  item
                )
              }}
            </p>
          </div>
        </article>
      </div>
    </section>

    <section
		  v-if="checkResult"
		  class="mt-6
		         grid
		         gap-3
		         sm:grid-cols-3"
		>
		  <div
		    class="rounded-[20px]
		           bg-emerald-50
		           p-4"
		  >
		    <p
		      class="text-2xl
		             font-semibold
		             text-emerald-700"
		    >
		      {{ checkResult.ready }}
		    </p>

		    <p
		      class="mt-1
		             text-xs
		             text-emerald-700/70"
		    >
		      Listos
		    </p>
		  </div>


		  <div
		    class="rounded-[20px]
		           bg-amber-50
		           p-4"
		  >
		    <p
		      class="text-2xl
		             font-semibold
		             text-amber-700"
		    >
		      {{ checkResult.conflicts }}
		    </p>

		    <p
		      class="mt-1
		             text-xs
		             text-amber-700/70"
		    >
		      Conflictos
		    </p>
		  </div>


		  <div
		    class="rounded-[20px]
		           bg-red-50
		           p-4"
		  >
		    <p
		      class="text-2xl
		             font-semibold
		             text-red-600"
		    >
		      {{ checkResult.blocked }}
		    </p>

		    <p
		      class="mt-1
		             text-xs
		             text-red-600/70"
		    >
		      Bloqueados
		    </p>
		  </div>
		</section>

    <!-- FUTURE SYNC BUTTON -->
    <section
      v-if="items.length"
      class="sticky
             bottom-4
             mt-8
             flex
             justify-end"
    >
      <button
			  class="rounded-full
			         bg-[#252525]
			         px-6 py-3
			         text-sm
			         font-semibold
			         text-white
			         shadow-xl
			         disabled:cursor-not-allowed
			         disabled:opacity-30"
			  :disabled="
			    selectedCount === 0 ||
			    checking ||
			    syncing
			  "
			  @click="
			    checkResult?.can_sync
			      ? syncSelected()
			      : checkSelected()
			  "
			>
			  {{
			    syncing
			      ? 'Sincronizando…'
			      : checking
			        ? 'Revisando…'
			        : checkResult?.can_sync
			          ? 'Sincronizar seleccionados'
			          : 'Revisar selección'
			  }}
			</button>
    </section>
  </div>
</template>