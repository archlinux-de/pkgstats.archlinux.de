<template>
  <div>
    <nav class="navbar navbar-expand-sm navbar-dark bg-dark navbar-border-brand mb-4">
      <router-link :to="{name: 'start'}" class="navbar-brand" href="">
        <picture>
          <source media="(min-width: 576px) and (max-width: 768px)"
                  srcset="@/images/archicon.svg 190w">
          <img alt="Arch Linux" height="40" src="@/images/archlogo.svg">
        </picture>
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

    <footer class="container-fluid mt-auto py-3 text-right">
      <router-link :to="{name: 'privacy-policy'}">Privacy policy</router-link>
      <router-link :to="{name: 'impressum'}">Impressum</router-link>
    </footer>
  </div>
</template>

<style lang="scss">
  @import "@/css/archlinux-bootstrap";

  .navbar-border-brand {
    border-bottom: 0.313rem solid $primary;
  }

  .nav-link {
    outline: 0;
  }

  @import "~bootstrap/scss/bootstrap";
</style>

<script>
  import 'bootstrap'
  import 'popper.js'

  export default {
    name: 'App',
    metaInfo () {
      return {
        title: 'Statistics',
        titleTemplate: '%s - pkgstats',
        meta: [{ vmid: 'robots', name: 'robots', content: 'index,follow' }],
        link: [{ rel: 'icon', href: require('@/images/archicon.svg'), sizes: 'any', type: 'image/svg+xml' }]
      }
    }
  }
</script>
