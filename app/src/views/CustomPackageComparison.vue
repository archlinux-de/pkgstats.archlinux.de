<template>
  <main class="container" role="main">
    <h1 class="mb-4">Package statistics & comparison</h1>

    <h2>Package comparison</h2>
    <div style="margin-bottom: 32px;">
      <div class="mb-2" v-if="selectedPackages.length > 0">
        <div :key="pkgName" v-for="pkgName in selectedPackages" class="pkg-badge">
          <span class="pkg-badge-content">{{pkgName}}</span>
          <button class="btn btn-secondary pkg-badge-button" @click="togglePackageSelected(pkgName)">X</button>
        </div>
      </div>
      <div v-else>
        No packages selected. Use the search below to add up to 10 packages
        and allow the generation of a comparison graph over time.
      </div>
      <a :href="customCompareChartLink" target="_blank" style="display: block;">
        Show graph
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-up-right" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M6.364 13.5a.5.5 0 0 0 .5.5H13.5a1.5 1.5 0 0 0 1.5-1.5v-10A1.5 1.5 0 0 0 13.5 1h-10A1.5 1.5 0 0 0 2 2.5v6.636a.5.5 0 1 0 1 0V2.5a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v10a.5.5 0 0 1-.5.5H6.864a.5.5 0 0 0-.5.5"/>
          <path fill-rule="evenodd" d="M11 5.5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793l-8.147 8.146a.5.5 0 0 0 .708.708L10 6.707V10.5a.5.5 0 0 0 1 0z"/>
        </svg>
      </a>
    </div>

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
          <th scope="col">Compare</th>
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
          <td>
            <button class="btn" @click="togglePackageSelected(pkg.name)">
              <template v-if="selectedPackages.includes(pkg.name)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                  <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                </svg>
              </template>
              <template v-else>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                  <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                </svg>
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

const query = useRouteQuery('query', useRouteHash('').value.replace(/^#query=/, ''))
const offset = ref(0)
const limit = ref(60)

const selectedPackages = ref([])
const customCompareChartLink = computed(() => ('/compare/packages#packages=' + selectedPackages.value.join(',')))

const togglePackageSelected = (pkgName) => {
  if (selectedPackages.value.includes(pkgName)) {
    selectedPackages.value = selectedPackages.value.filter((pkg) => pkg !== pkgName)
  } else {
    selectedPackages.value.push(pkgName)
  }
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

<style scoped lang="scss">
.pkg-badge {
  border: 2px solid #08c;
  margin: 4px 4px 4px 0;
  display: inline-block;
  .pkg-badge-content {
    padding: 4px 8px;
  }
  .pkg-badge-button {
    padding: 4px 8px;
    border-left: 2px solid #08c;
  }
}

</style>
