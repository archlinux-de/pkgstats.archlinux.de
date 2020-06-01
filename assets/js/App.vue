<template>
  <div>
    <b-navbar class="navbar-border-brand nav-no-outline mb-4" toggleable="sm" type="dark" variant="dark">
      <b-navbar-brand :to="{name: 'start'}">
        <img alt="Arch Linux" height="40" :src="logo"/>
      </b-navbar-brand>

      <b-navbar-toggle target="nav-collapse"></b-navbar-toggle>

      <b-collapse id="nav-collapse" is-nav>
        <b-navbar-nav class="ml-auto mr-4">
          <b-nav-item :to="{name: 'start'}" class="ml-3 font-weight-bold" exact>Start</b-nav-item>
          <b-nav-item :to="{name: 'packages'}" class="ml-3 font-weight-bold">Packages</b-nav-item>
          <b-nav-item :to="{name: 'fun'}" class="ml-3 font-weight-bold">Fun</b-nav-item>
          <b-nav-item :to="{name: 'api-doc'}" class="ml-3 font-weight-bold">API</b-nav-item>
        </b-navbar-nav>
      </b-collapse>
    </b-navbar>

    <router-view/>

    <footer>
      <b-nav align="right" class="nav-no-outline">
        <b-nav-item :to="{name: 'privacy-policy'}">Privacy policy</b-nav-item>
        <b-nav-item :to="{name: 'impressum'}">Impressum</b-nav-item>
      </b-nav>
    </footer>
  </div>
</template>

<style lang="scss">
  @import "@/css/archlinux-bootstrap";

  .navbar-border-brand {
    border-bottom: 0.313rem solid $primary;
  }

  .nav-no-outline {
    a:focus,
    button:focus {
      outline: 0;
    }
  }

  @import "~bootstrap";
  @import "~bootstrap-vue";
</style>

<script>
import LogoImage from '@/images/archlogo.svg'
import IconImage from '@/images/archicon.svg'

export default {
  name: 'App',
  metaInfo () {
    return {
      title: 'Statistics',
      titleTemplate: '%s - pkgstats',
      meta: [{ vmid: 'robots', name: 'robots', content: 'index,follow' }],
      link: [{ rel: 'icon', href: this.icon, sizes: 'any', type: 'image/svg+xml' }]
    }
  },
  data () {
    return {
      logo: LogoImage,
      icon: IconImage
    }
  }
}
</script>
