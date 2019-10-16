<template>
  <div>
    <nav class="navbar navbar-expand-sm navbar-dark bg-dark navbar-border-brand mb-4">
      <router-link :to="{name: 'start'}" class="navbar-brand" tabindex="-1">
        <img alt="Arch Linux" height="40" src="@/images/archlogo.svg">
      </router-link>

      <button aria-controls="navbar" aria-expanded="false" aria-label="Navigation" class="navbar-toggler"
              data-target="#navbar" data-toggle="collapse" type="button">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbar">
        <ul class="navbar-nav ml-auto mr-4">
          <li class="nav-item ml-3">
            <router-link :to="{name: 'start'}" class="nav-link font-weight-bold" exact>Start</router-link>
          </li>
          <li class="nav-item ml-3">
            <router-link :to="{name: 'packages'}" class="nav-link font-weight-bold">Packages</router-link>
          </li>
          <li class="nav-item ml-3">
            <router-link :to="{name: 'fun'}" class="nav-link font-weight-bold">Fun</router-link>
          </li>
          <li class="nav-item ml-3">
            <router-link :to="{name: 'api-doc'}" class="nav-link font-weight-bold">API</router-link>
          </li>
        </ul>
      </div>
    </nav>

    <router-view/>

    <footer class="nav justify-content-end">
      <router-link :to="{name: 'privacy-policy'}" class="nav-link">Privacy policy</router-link>
      <router-link :to="{name: 'impressum'}" class="nav-link">Impressum</router-link>
    </footer>
  </div>
</template>

<style lang="scss">
  @import "@/css/archlinux-bootstrap";

  .navbar-border-brand {
    border-bottom: 0.313rem solid $primary;
  }

  a.navbar-brand:focus,
  button.navbar-toggler:focus,
  a.nav-link:focus {
    outline: 0;
  }

  @import "bootstrap";
</style>

<script>
import { Collapse } from 'bootstrap.native'

export default {
  name: 'App',
  metaInfo () {
    return {
      title: 'Statistics',
      titleTemplate: '%s - pkgstats',
      meta: [{ vmid: 'robots', name: 'robots', content: 'index,follow' }],
      link: [{ rel: 'icon', href: require('@/images/archicon.svg'), sizes: 'any', type: 'image/svg+xml' }]
    }
  },
  methods: {
    navbarToggle: null
  },
  mounted () {
    this.navbarToggle = new Collapse(this.$el.querySelector('.navbar-toggler'))
  }
}
</script>
