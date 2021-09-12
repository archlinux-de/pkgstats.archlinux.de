<template>
  <div class="container" role="main">
    <h1 class="mb-3">Compare Packages</h1>
    <p class="mb-3">Relative usage of packages</p>
    <package-chart :limit="0" :packages="packages" :start-month="0"></package-chart>
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import PackageChart from '../components/PackageChart'

const packages = (() => {
  let packages = useRoute().hash
    .replace(/^#packages=/, '')
    .split(',')
    .filter(pkg => pkg.match(/^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]+$/))

  packages = Array.from(new Set(packages)).sort()
  // limit the number of line graphs
  packages = packages.slice(0, 10)

  const canonicalHash = '#packages=' + packages.join(',')
  if (useRoute().hash !== canonicalHash) {
    useRouter().replace({ name: 'compare', hash: canonicalHash })
  }

  return packages
})()

useHead({ title: 'Compare Packages' })
</script>
