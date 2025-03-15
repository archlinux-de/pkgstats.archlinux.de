<template>
  <div class="container" role="main">
    <h1 class="mb-3">Compare Packages</h1>
    <p class="mb-3">Relative usage of packages</p>
    <div  v-if="packages.length > 0">
      <package-chart :limit="0" :packages="packages" :start-month="0" class="mb-4"></package-chart>
      <router-link data-test-id="edit-selection-cta" class="btn btn-primary" :to="{name: 'packages', query: { compare: compareQuery} }">Edit selection</router-link>
    </div>
    <div v-else class="alert alert-danger" role="alert">
      <h4 class="alert-heading">No packages defined</h4>
      <p>Provide at least one package name to compare</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import PackageChart from '../components/PackageChart'

const route = useRoute()
const router = useRouter()

const packages = computed(() => {
  const tempPackages = route.hash
    .replace(/^#packages=/, '')
    .split(',')
    .filter(value => value.match(/^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]*$/))

  return [...new Set(tempPackages)].sort().slice(0, 10)
}
)

const compareQuery = computed(() => packages.value.join(','))

const unwatch = watch(packages, () => {
  const canonicalHash = '#packages=' + packages.value.join(',')

  if (route.name === 'compare' && route.hash !== canonicalHash) {
    router.replace({ name: 'compare', hash: canonicalHash })
  }
}, { immediate: true })

onBeforeUnmount(() => {
  unwatch()
})

useHead({ title: 'Compare Packages' })
</script>
