<template>
  <main class="container" role="main">
    <h1 class="mb-4">Package statistics & comparison</h1>

    <h2>Package comparison</h2>
    <div v-if="isFinished2 && error2.length === 0" class="mb-4">
      <div class="mb-2" v-if="selectedPackages.length > 0">
        <table class="table table-striped table-borderless table-sm">
          <thead>
          <tr>
            <th scope="col">Package</th>
            <th scope="col">Popularity</th>
            <th scope="col" class="d-none d-lg-block text-center">Compare</th>
          </tr>
          </thead>
          <tbody>
          <tr :key="id" v-for="(pkg, id) in selectedPackages">
            <td class="text-nowrap" data-test="package-name-in-comparison-table">
              <router-link :to="{name: 'package', params: {package: pkg.name}}">{{ pkg.name }}</router-link>
            </td>
            <td class="w-75">
              <div class="progress bg-transparent progress-large"
                   :title="pkg.popularity + '%'">
                <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                     :style="`width: ${pkg.popularity}%`"
                     :aria-valuenow="pkg.popularity"
                     v-text="(pkg.popularity > 5 ? pkg.popularity + '%' : '')"></div>
              </div>
            </td>
            <td class="align-middle">
              <button data-test="toggle-pkg-in-comparison-table" class="d-flex btn w-100 p-0 b-none" @click="togglePackageSelected(pkg.name)">
                <template v-if="isPackageSelected(pkg.name)">
                  <span v-html="trash" class="d-inline-flex text-primary justify-content-center w-100"></span>
                </template>
                <template v-else>
                  <span v-html="plus" class="d-inline-flex text-primary justify-content-center w-100"></span>
                </template>
              </button>
            </td>
          </tr>
          </tbody>
        </table>
        <router-link
          v-if="selectedPackages.length > 1 && selectedPackages.length < 11"
          class="d-inline-flex btn btn-primary"
          :to="{ name: 'compare', hash: '#packages=' + Array.from(selectedPackageNames).sort() }"
          data-test-name="comparison-graph-link"
        >
          <span>Compare</span>
        </router-link>
        <div v-if="selectedPackages.length > 10" role="alert" class="alert alert-info mb-4" data-test-name="compare-too-many-packages-hint">
            You can only compare up to 10 packages.
        </div>
      </div>
      <div v-else class="mb-2">
        No packages selected. Use the search below to add packages
        and allow the generation of a comparison graph over time.
      </div>
    </div>

    <loading-spinner v-if="isFetching2"></loading-spinner>

    <h2>Package Search</h2>
    <div class="input-group mb-4">
      <span class="input-group-text" id="package-search-label">Package name</span>
      <input class="form-control" type="search"
             placeholder="Search packages" aria-describedby="package-search-label"
             v-model="query">
    </div>

    <div v-if="data.packagePopularities && data.packagePopularities.length > 0">
      <table class="table table-striped table-borderless table-sm">
        <thead>
        <tr>
          <th scope="col">Package</th>
          <th scope="col">Popularity</th>
          <th scope="col" class="d-none d-lg-block text-center">Compare</th>
        </tr>
        </thead>
        <tbody>
        <tr :key="id" v-for="(pkg, id) in data.packagePopularities" :data-test-name="pkg.name">
          <td class="text-nowrap">
            <router-link :to="{name: 'package', params: {package: pkg.name}}">{{ pkg.name }}</router-link>
          </td>
          <td class="w-75">
            <div class="progress bg-transparent progress-large"
                 :title="pkg.popularity + '%'">
              <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                   :style="`width: ${pkg.popularity}%`"
                   :aria-valuenow="pkg.popularity"
                   v-text="(pkg.popularity > 5 ? pkg.popularity + '%' : '')"></div>
            </div>
          </td>
          <td class="align-middle">
            <button data-test="toggle-pkg-in-popularity-table" class="d-flex btn w-100 p-0 b-none" @click="togglePackageSelected(pkg.name)">
              <template v-if="isPackageSelected(pkg.name)">
                <span v-html="trash" class="text-primary d-inline-flex justify-content-center w-100"></span>
              </template>
              <template v-else>
                <span v-html="plus" class="text-primary d-inline-flex justify-content-center w-100"></span>
              </template>
            </button>
          </td>
        </tr>
        </tbody>
      </table>
      <div ref="end"></div>
    </div>

    <loading-spinner v-if="isFetching"></loading-spinner>
    <div role="alert" class="alert alert-danger" v-if="error">{{ error }}</div>

    <div role="alert" v-if="isFinished && !error && data.total === data.count" class="alert alert-info mb-4">
      {{ data.total }} packages found
    </div>

  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useIntersectionObserver } from '@vueuse/core'
import { useHead } from '@vueuse/head'
import { useRouteHash, useRouteQuery } from '@vueuse/router'
import LoadingSpinner from '../components/LoadingSpinner'
import { useFetchPackageList } from '../composables/useFetchPackageList'
import trash from 'bootstrap-icons/icons/trash.svg?raw'
import plus from 'bootstrap-icons/icons/plus-lg.svg?raw'
import { useFetchPackagesPopularity } from '../composables/useFetchPackagesPopularity'

const query = useRouteQuery('query', useRouteHash('').value.replace(/^#query=/, ''))
const offset = ref(0)
const limit = ref(60)

const compare = useRouteQuery('compare', '')
const tmp = compare.value.split(',').filter(value => value.match(/^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]+$/))
// remove duplicates if the user enters packages in the url directly
compare.value = [...new Set(tmp)].sort().slice(0, 10).join(',')

const selectedPackageNames = computed(() => {
  if (compare.value) {
    return [...new Set(compare.value.split(','))].slice(0, 10)
  }
  return []
})

const { isFinished: isFinished2, isFetching: isFetching2, data: selectedPackages, error: error2 } = useFetchPackagesPopularity(selectedPackageNames)

const isPackageSelected = (pkgName) => selectedPackageNames.value.includes(pkgName)

const togglePackageSelected = (pkgName) => {
  const pkgIndex = selectedPackageNames.value.indexOf(pkgName)
  if (pkgIndex > -1) {
    // Remove element as computed array is readonly
    selectedPackageNames.value.splice(pkgIndex, 1)
  } else {
    selectedPackageNames.value.push(pkgName)
  }

  compare.value = [...new Set(selectedPackageNames.value)].sort().slice(0, 10).join(',')
}

const { isFinished, isFetching, data, error } = useFetchPackageList(query, offset, limit)

const end = ref(null)

const { stop } = useIntersectionObserver(
  end,
  ([entry]) => {
    if (entry.intersectionRatio <= 0) {
      return
    }
    if (error.value || data.value.count >= data.value.total) {
      stop()
      return
    }
    offset.value += limit.value
  },
  { rootMargin: '0px 0px 640px 0px' }
)

onBeforeUnmount(() => {
  stop()
})

useHead({
  title: 'Package statistics',
  link: [{ rel: 'canonical', href: window.location.orient + useRoute().path }],
  meta: [{
    name: 'robots',
    content: computed(() => (data.value?.count === 0 || error.value ? 'noindex' : 'index') + ',follow')
  }]
})
</script>
