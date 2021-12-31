<template>
  <div id="page">
    <nav class="navbar navbar-expand-md navbar-dark navbar-border-brand bg-dark nav-no-outline mb-4">
      <div class="container-fluid">
        <router-link :to="{name: 'start'}" class="navbar-brand">
          <img alt="Arch Linux" height="40" width="190" :src="LogoImage" class="d-inline-block align-text-top"/>
        </router-link>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#archlinux-navbar"
                aria-controls="archlinux-navbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="archlinux-navbar">
          <ul class="navbar-nav ms-auto mb-2 mb-md-0">
            <li class="nav-item">
              <router-link :to="{name: 'start'}" exact class="nav-link ms-3 fw-bold">Start</router-link>
            </li>
            <li class="nav-item">
              <router-link :to="{name: 'packages'}" class="nav-link ms-3 fw-bold">Packages</router-link>
            </li>
            <li class="nav-item">
              <router-link :to="{name: 'fun'}" class="nav-link ms-3 fw-bold">Fun</router-link>
            </li>
            <li class="nav-item">
              <router-link :to="{name: 'api-doc'}" class="nav-link ms-3 fw-bold">API</router-link>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <router-view id="content"/>

    <footer id="footer">
      <nav class="nav nav-no-outline justify-content-end mt-4">
        <router-link class="nav-link" :to="{name: 'privacy-policy'}">Privacy policy</router-link>
        <router-link class="nav-link" :to="{name: 'impressum'}">Impressum</router-link>
      </nav>
    </footer>
  </div>
</template>

<style lang="scss">
@import "./assets/css/archlinux-bootstrap";
@import "./assets/css/import-bootstrap";

.navbar-border-brand {
  border-bottom: 0.313rem solid $primary;
}

.nav-no-outline a:focus {
  outline: 0;
}

#page {
  position: relative;
  min-height: 100vh;
}

#content {
  padding-bottom: 2.3rem;
}

#footer {
  position: absolute;
  bottom: 0;
  width: 100%;
  height: 2.3rem;
}

.progress-large {
  height: 2em;
}
</style>

<script setup>
import 'bootstrap/js/src/collapse'
import LogoImage from './assets/images/archlogo.svg'
import IconImage from './assets/images/archicon.svg'
import { onMounted } from 'vue'
import { useHead } from '@vueuse/head'

useHead({
  title: 'pkgstats',
  meta: [
    { name: 'robots', content: 'index,follow' },
    { name: 'theme-color', content: '#333' }
  ],
  link: [
    { rel: 'icon', href: IconImage, sizes: 'any', type: 'image/svg+xml' },
    { rel: 'manifest', href: '/manifest.webmanifest' }
  ]
})

onMounted(() => {
  if (process.env.NODE_ENV === 'production' && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js')
    })
  }
})
</script>
