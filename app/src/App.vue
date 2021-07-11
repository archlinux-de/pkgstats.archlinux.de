<template>
  <div id="page">
    <b-navbar class="navbar-border-brand nav-no-outline mb-4" toggleable="sm" type="dark" variant="dark">
      <b-navbar-brand :to="{name: 'start'}">
        <img alt="Arch Linux" height="40" width="190" :src="logo"/>
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

    <router-view id="content"/>

    <footer id="footer">
      <b-nav align="right" class="nav-no-outline">
        <b-nav-item :to="{name: 'privacy-policy'}">Privacy policy</b-nav-item>
        <b-nav-item :to="{name: 'impressum'}">Impressum</b-nav-item>
      </b-nav>
    </footer>
  </div>
</template>

<style lang="scss">
  @import "./assets/css/archlinux-bootstrap";
  @import "~bootstrap/scss/bootstrap.scss";
  @import "~bootstrap-vue/src/index.scss";

  .navbar-border-brand {
    border-bottom: 0.313rem solid $primary;
  }

  .nav-no-outline {
    a:focus,
    button:focus {
      outline: 0;
    }
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
</style>

<script>
import { BCollapse, BNav, BNavbar, BNavbarBrand, BNavbarNav, BNavbarToggle, BNavItem } from 'bootstrap-vue'
import LogoImage from './assets/images/archlogo.svg'
import IconImage from './assets/images/archicon.svg'

export default {
  name: 'App',
  components: {
    BNavbar,
    BNavbarBrand,
    BNavbarToggle,
    BCollapse,
    BNavbarNav,
    BNavItem,
    BNav
  },
  metaInfo () {
    return {
      title: 'Statistics',
      titleTemplate: '%s - pkgstats',
      meta: [
        { vmid: 'robots', name: 'robots', content: 'index,follow' },
        { name: 'theme-color', content: '#333' }
      ],
      link: [
        { rel: 'icon', href: this.icon, sizes: 'any', type: 'image/svg+xml' },
        { rel: 'manifest', href: '/manifest.webmanifest' }
      ]
    }
  },
  data () {
    return {
      logo: LogoImage,
      icon: IconImage
    }
  },
  mounted () {
    if (process.env.NODE_ENV === 'production' && 'serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
      })
    }
  }
}
</script>
