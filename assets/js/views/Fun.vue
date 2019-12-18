<template>
  <b-container fluid role="main" tag="main">
    <h1 class="mb-4">Fun statistics</h1>
    <div :key="title" v-for="(pkgs, title) in config">
      <h2>
        <router-link :to="{ name: 'compare', hash: '#packages=' + Array.from(pkgs).sort() }">{{ title }}</router-link>
      </h2>
      <packages-bar-chart :packages="pkgs"></packages-bar-chart>
    </div>
  </b-container>
</template>

<script>
import FunConfig from '@/js/config/fun'
import PackagesBarChart from '@/js/components/PackagesBarChart'

export default {
  name: 'Fun',
  components: { PackagesBarChart },
  data () {
    return { config: FunConfig }
  },
  metaInfo: { title: 'Fun statistics' }
}
</script>
