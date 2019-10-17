<template>
  <div>
    <div :key="title" v-for="(pkgs, title) in config">
      <h2>
        <router-link :to="{ name: 'compare', hash: '#packages=' + Array.from(pkgs).sort() }">{{ title }}</router-link>
      </h2>
      <packages-bar-chart :packages="pkgs"></packages-bar-chart>
    </div>
  </div>
</template>

<script>
import FunConfig from '@/js/config/fun'
import PackagesBarChart from './PackagesBarChart'

export default {
  name: 'FunStatistics',
  data () {
    return {
      config: FunConfig
    }
  },
  components: {
    PackagesBarChart
  }
}
</script>
