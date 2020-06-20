<template>
  <b-container role="main" tag="main">
    <h1 class="mb-3">Compare Packages</h1>
    <p class="mb-3">Relative usage of packages</p>
    <package-chart :limit="0" :packages="packages" :start-month="0"></package-chart>
  </b-container>
</template>

<script>
import PackageChart from '@/components/PackageChart'

export default {
  name: 'Compare',
  components: {
    PackageChart
  },
  data () {
    return {
      packages: (() => {
        let packages = this.$route.hash
          .replace(/^#packages=/, '')
          .split(',')
          .filter(pkg => pkg.match(/^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]+$/))

        packages = Array.from(new Set(packages)).sort()
        // limit the number of line graphs
        packages = packages.slice(0, 10)

        const canonicalHash = '#packages=' + packages.join(',')
        if (this.$route.hash !== canonicalHash) {
          this.$router.replace({ name: 'compare', hash: canonicalHash })
        }

        return packages
      })()
    }
  },
  metaInfo: {
    title: 'Compare Packages',
    meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }]
  }
}
</script>
