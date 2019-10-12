<template>
  <main class="container" role="main">
    <h1 class="mb-3">{{ $route.params.package }}</h1>
    <p class="mb-3">Relative usage of {{ $route.params.package }}</p>
    <package-chart :limit="0" :packages="[$route.params.package]" :start-month="0"></package-chart>
  </main>
</template>

<script>
  import PackageChart from '@/js/components/PackageChart'

  export default {
    name: 'Package',
    components: {
      PackageChart
    },
    metaInfo () { return { title: this.$route.params.package } }
  }
</script>
