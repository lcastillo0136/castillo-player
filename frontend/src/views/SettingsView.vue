<script setup>
import {
  ref
} from 'vue'

import {
  RefreshCw,
  Settings
} from 'lucide-vue-next'


const loading = ref(true)

const frameKey = ref(0)

const settingsFrame = ref(null)


function reloadSettings() {
  loading.value = true

  frameKey.value++
}


function injectCastilloStyles() {
  const iframe =
    settingsFrame.value


  if (!iframe) {
    return
  }


  try {
    const document =
      iframe.contentDocument ||
      iframe.contentWindow?.document


    if (!document) {
      return
    }


    /*
     * Evitar insertar el mismo estilo
     * más de una vez.
     */
    const previous =
      document.getElementById(
        'castillo-moode-theme'
      )


    if (previous) {
      previous.remove()
    }


    const style =
      document.createElement(
        'style'
      )


    style.id =
      'castillo-moode-theme'


    style.textContent = `
      /*
       * ====================================
       * CASTILLO PLAYER
       * Theme visual para configuración moOde
       * ====================================
       */

      :root {
        --castillo-bg: #faf9f7;
        --castillo-surface: #f2f0ed;
        --castillo-surface-2: #ebe8e4;

        --castillo-dark: #252525;
        --castillo-sidebar: #625d5a;

        --castillo-orange: #be5c2b;
        --castillo-yellow: #f2f478;

        --castillo-text: #252525;
        --castillo-muted: rgba(37, 37, 37, .48);

        --castillo-border: rgba(0, 0, 0, .07);
      }
      :root body {	
        --config-menu-bg-color: rgba(50, 50, 50, 0.95);
        --config-menu-bg-color: rgba(255, 255, 255, 1);
      }
			:root body .dropdown-menu>li>a:focus {
				--accentxta: var(--castillo-yellow);
			}

      /*
       * BASE
       */
      html,
      body {
        background:
          var(--castillo-bg)
          !important;

        color:
          var(--castillo-text)
          !important;

        font-family:
          Poppins,
          "Helvetica Neue",
          Arial,
          sans-serif
          !important;
      }


      body {
        scrollbar-color:
          rgba(37, 37, 37, .20)
          transparent;
      }


      body::-webkit-scrollbar {
        width: 8px;
        height: 8px;
      }


      body::-webkit-scrollbar-track {
        background: transparent;
      }


      body::-webkit-scrollbar-thumb {
        background:
          rgba(37, 37, 37, .18);

        border-radius: 999px;
      }


      /*
       * CONTENEDORES GENERALES
       */
      #container,
      #main-content,
      #content,
      .content,
      .container,
      .container-fluid,
      .ui-content {
        background:
          var(--castillo-bg)
          !important;

        color:
          var(--castillo-text)
          !important;
      }


      /*
       * BARRAS / NAV
       */
      header,
      .navbar,
      .navbar-default,
      .navbar-inverse {
        background:
          var(--castillo-bg)
          !important;

        border-color:
          var(--castillo-border)
          !important;

        box-shadow:
          none
          !important;
      }


      /*
       * TÍTULOS
       */
      h1,
      h2,
      h3,
      h4,
      h5,
      h6 {
        color:
          var(--castillo-dark)
          !important;

        font-family:
          Poppins,
          "Helvetica Neue",
          Arial,
          sans-serif
          !important;

        font-weight:
          600
          !important;
      }


      /*
       * TEXTO
       */
      p,
      span,
      label,
      legend,
      td,
      th,
      li {
        color:
          inherit;
      }


      label {
        color:
          rgba(37, 37, 37, .72)
          !important;

        font-weight:
          500
          !important;
      }


      /*
       * LINKS
       */
      a {
        color:
          var(--castillo-orange)
          !important;

        text-decoration:
          none
          !important;
      }


      a:hover {
        color:
          #93431f
          !important;
      }


      /*
       * SEPARADORES
       */
      hr {
        border-color:
          var(--castillo-border)
          !important;

        opacity: 1
          !important;
      }


      /*
       * FORMULARIOS
       */
      input[type="text"],
      input[type="number"],
      input[type="password"],
      input[type="email"],
      input[type="url"],
      input[type="search"],
      textarea,
      select,
      .form-control {
        background:
          var(--castillo-surface)
          !important;

        color:
          var(--castillo-dark)
          !important;

        border:
          1px solid
          var(--castillo-border)
          !important;

        border-radius:
          11px
          !important;

        box-shadow:
          none
          !important;

        outline:
          none
          !important;

        transition:
          border-color .15s ease,
          box-shadow .15s ease,
          background .15s ease
          !important;
      }


      input:focus,
      textarea:focus,
      select:focus,
      .form-control:focus {
        background:
          #ffffff
          !important;

        border-color:
          rgba(190, 92, 43, .40)
          !important;

        box-shadow:
          0 0 0 3px
          rgba(190, 92, 43, .10)
          !important;
      }


      select option {
        background:
          #ffffff
          !important;

        color:
          var(--castillo-dark)
          !important;
      }


      /*
       * BOTONES
       */
      button,
      .btn,
      input[type="button"],
      input[type="submit"] {
        border:
          0
          !important;

        border-radius:
          11px
          !important;

        background:
          var(--castillo-surface-2)
          !important;

        color:
          rgba(37, 37, 37, .70)
          !important;

        box-shadow:
          none
          !important;

        font-family:
          Poppins,
          "Helvetica Neue",
          Arial,
          sans-serif
          !important;

        font-weight:
          500
          !important;

        transition:
          background .15s ease,
          color .15s ease,
          transform .15s ease
          !important;
      }


      button:hover,
      .btn:hover,
      input[type="button"]:hover,
      input[type="submit"]:hover {
        background:
          var(--castillo-dark)
          !important;

        color:
          #ffffff
          !important;
      }


      button:active,
      .btn:active {
        transform:
          scale(.97);
      }


      /*
       * BOTONES PRINCIPALES
       */
      .btn-primary,
      .btn-success {
        background:
          var(--castillo-dark)
          !important;

        color:
          #ffffff
          !important;
      }


      .btn-primary:hover,
      .btn-success:hover {
        background:
          var(--castillo-orange)
          !important;
      }


      /*
       * ACCIONES PELIGROSAS
       *
       * Conservamos el rojo porque
       * reiniciar/apagar/eliminar debe
       * seguir siendo visualmente evidente.
       */
      .btn-danger,
      .btn-error {
        background:
          #fff0ef
          !important;

        color:
          #c84238
          !important;
      }


      .btn-danger:hover,
      .btn-error:hover {
        background:
          #c84238
          !important;

        color:
          #ffffff
          !important;
      }


      /*
       * PANELES / CARDS
       */
      .panel,
      .well,
      .card,
      .modal-content,
      fieldset {
        background:
          var(--castillo-surface)
          !important;

        color:
          var(--castillo-dark)
          !important;

        border:
          1px solid
          var(--castillo-border)
          !important;

        border-radius:
          16px
          !important;

        box-shadow:
          none
          !important;
      }


      .panel-heading,
      .card-header,
      .modal-header {
        background:
          transparent
          !important;

        color:
          var(--castillo-dark)
          !important;

        border-color:
          var(--castillo-border)
          !important;
      }


      /*
       * TABS DE CONFIGURACIÓN
       */
      .nav-tabs,
      .nav-pills,
      .nav {
        background:
          transparent
          !important;

        border:
          0
          !important;
      }


      .nav-tabs > li > a,
      .nav-pills > li > a,
      .nav > li > a {
        background:
          var(--castillo-surface)
          !important;

        color:
          rgba(37, 37, 37, .60)
          !important;

        border:
          0
          !important;

        border-radius:
          9px
          !important;

        margin:
          2px
          !important;

        transition:
          background .15s ease,
          color .15s ease
          !important;
      }


      .nav-tabs > li > a:hover,
      .nav-pills > li > a:hover,
      .nav > li > a:hover {
        background:
          var(--castillo-surface-2)
          !important;

        color:
          var(--castillo-dark)
          !important;
      }


      .nav-tabs > li.active > a,
      .nav-tabs > li.active > a:hover,
      .nav-pills > li.active > a,
      .nav > li.active > a {
        background:
          var(--castillo-dark)
          !important;

        color:
          var(--castillo-yellow)
          !important;
      }


      /*
       * TABLAS
       */
      table {
        color:
          var(--castillo-dark)
          !important;

        background:
          transparent
          !important;
      }


      table tr {
        border-color:
          var(--castillo-border)
          !important;
      }


      table td,
      table th {
        border-color:
          var(--castillo-border)
          !important;
      }

      #config-back, #config-home { display: none !important; }
			div#config-tabs {
			    position: relative;
			    top: 0;
			    left: 1rem;
			    transform: translate(0px, 0px);
			    display: flex;
			    gap: 6px;
			}
      /*
       * DROPDOWNS
       */
      .dropdown-menu {
        background:
          #ffffff
          !important;

        color:
          var(--castillo-dark)
          !important;

        border:
          1px solid
          var(--castillo-border)
          !important;

        border-radius:
          14px
          !important;

        box-shadow:
          0 14px 40px
          rgba(0, 0, 0, .12)
          !important;
      }


      .dropdown-menu > li > a {
        color:
          rgba(37, 37, 37, .70)
          !important;

        border-radius:
          8px
          !important;
      }


      .dropdown-menu > li > a:hover {
        background:
          var(--castillo-surface)
          !important;

        color:
          var(--castillo-orange)
          !important;
      }


      /*
       * MODALES
       */
      .modal-backdrop {
        background:
          #252525
          !important;
      }


      .modal-content {
        background:
          #faf9f7
          !important;

        border-radius:
          22px
          !important;

        box-shadow:
          0 25px 80px
          rgba(0, 0, 0, .22)
          !important;
      }


      /*
       * BADGES / LABELS
       */
      .badge,
      .label {
        border-radius:
          999px
          !important;
      }


      /*
       * ALERTAS
       */
      .alert {
        border:
          0
          !important;

        border-radius:
          14px
          !important;
      }


      /*
       * Pequeña suavización general.
       */
      * {
        scrollbar-width:
          thin;
      }
    `


    document.head.appendChild(
      style
    )

  } catch (error) {
    console.warn(
      '[Castillo] No se pudo aplicar el tema a moOde:',
      error
    )
  }
}


function onFrameLoad() {
  loading.value = false


  /*
   * Aplicar inmediatamente.
   */
  injectCastilloStyles()


  /*
   * Algunas pantallas de moOde terminan
   * de construir elementos después del
   * evento load. El CSS ya es global,
   * pero repetimos la inyección por
   * seguridad.
   */
  window.setTimeout(
    injectCastilloStyles,
    300
  )
}
</script>


<template>
  <div
    class="flex
           min-h-full
           flex-col"
  >
    <!-- HEADER -->
    <header
      class="flex
             items-center
             justify-between
             border-b
             border-black/5
             pb-5"
    >
      <div>
        <p
          class="text-[10px]
                 font-semibold
                 uppercase
                 tracking-[0.18em]
                 text-[#be5c2b]"
        >
          Configuración
        </p>

        <h1
          class="mt-1
                 text-3xl
                 font-semibold
                 tracking-tight
                 text-[#252525]"
        >
          moOde
        </h1>

        <p
          class="mt-1
                 text-xs
                 text-black/35"
        >
          Audio, red, sistema y servicios
        </p>
      </div>


      <button
        class="flex
               h-10 w-10
               items-center
               justify-center
               rounded-[14px]
               bg-[#f0eeeb]
               text-black/45
               transition
               hover:bg-[#e7e3df]
               hover:text-[#be5c2b]
               active:scale-95"
        title="Recargar configuración"
        @click="reloadSettings"
      >
        <RefreshCw
          class="h-[18px] w-[18px]"
          :stroke-width="1.8"
        />
      </button>
    </header>


    <!-- MOODE -->
    <section
      class="relative
             mt-5
             flex-1
             overflow-hidden
             rounded-[24px]
             border
             border-black/[0.06]
             bg-[#faf9f7]"
    >
      <!-- LOADING -->
      <div
        v-if="loading"
        class="absolute
               inset-0
               z-10
               flex
               items-center
               justify-center
               bg-[#faf9f7]"
      >
        <div
          class="text-center"
        >
          <div
            class="mx-auto
                   flex
                   h-11 w-11
                   items-center
                   justify-center
                   rounded-[15px]
                   bg-[#f0eeeb]"
          >
            <Settings
              class="h-5 w-5
                     animate-pulse
                     text-[#be5c2b]"
            />
          </div>

          <p
            class="mt-3
                   text-xs
                   font-medium
                   text-black/45"
          >
            Cargando configuración…
          </p>
        </div>
      </div>


      <iframe
        ref="settingsFrame"
        :key="frameKey"
        src="/sys-config.php"
        title="Configuración de moOde"
        class="block
               h-[calc(100vh-190px)]
               min-h-[600px]
               w-full
               border-0
               bg-[#faf9f7]"
        @load="onFrameLoad"
      />
    </section>
  </div>
</template>