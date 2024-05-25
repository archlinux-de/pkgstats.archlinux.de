<template>
  <main class="container" role="main">
    <h1 class="mb-4">Package statistics & comparison</h1>

    <h2>Package comparison</h2>
    <div class="mb-4">
      <div class="mb-2" v-if="selectedPackages.length > 0">
        <table class="table table-striped table-borderless table-sm">
          <thead>
          <tr>
            <th scope="col">Package</th>
            <th scope="col">Popularity</th>
            <th scope="col">Compare</th>
          </tr>
          </thead>
          <tbody>
          <tr :key="id" v-for="(pkg, id) in selectedPackages">
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
              <button data-test="toggle-pkg-in-comparison-table" class="btn" @click="togglePackageSelected(pkg)">
                <template v-if="isPackageSelected(pkg)">
                  <span v-html="trash" class="text-primary"></span>
                </template>
                <template v-else>
                  <span v-html="plus" class="text-primary"></span>
                </template>
              </button>
            </td>
          </tr>
          </tbody>
        </table>
<!--        <div :key="pkg.name" v-for="pkg in selectedPackages" class="pkg-badge">-->
<!--          <span class="pkg-badge-content">{{pkg.name}}</span>-->
<!--          <button class="btn btn-secondary pkg-badge-button" @click="togglePackageSelected(pkg)">X</button>-->
<!--        </div>-->
      </div>
      <div v-else>
        No packages selected. Use the search below to add packages
        and allow the generation of a comparison graph over time.
      </div>
      <div>
        <a :href="customCompareChartLink"
           class="d-flex"
           :class="{disabled: selectedPackages.length < 1}"
           target="_blank"
           data-test-name="comparison-graph-link"
        >
          <span class="me-2">Show graph </span>
          <span class="d-flex" v-html="boxArrowUpRight"></span>
        </a>
      </div>
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
            <button data-test="toggle-pkg-in-popularity-table" class="btn" @click="togglePackageSelected(pkg)">
              <template v-if="isPackageSelected(pkg)">
                <span v-html="trash" class="text-primary"></span>
              </template>
              <template v-else>
                <span v-html="plus" class="text-primary"></span>
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
import plus from 'bootstrap-icons/icons/plus.svg?raw'
import boxArrowUpRight from 'bootstrap-icons/icons/box-arrow-up-right.svg?raw'

const query = useRouteQuery('query', useRouteHash('').value.replace(/^#query=/, ''))
const offset = ref(0)
const limit = ref(60)

const selectedPackages = ref([])
const customCompareChartLink = computed(
  () => ('/compare/packages#packages=' + selectedPackages.value.map((pkg) => pkg.name).join(','))
)
const togglePackageSelected = (pkg) => {
  if (selectedPackages.value.length > 0) {
    if (isPackageSelected(pkg)) {
      selectedPackages.value = selectedPackages.value.filter((selectedPkg) => selectedPkg.name !== pkg.name)
    } else {
      selectedPackages.value.push(pkg)
    }
  } else {
    selectedPackages.value.push(pkg)
  }
}

const isPackageSelected = (pkg) => {
  for (const selectedPackage of selectedPackages.value) {
    if (selectedPackage.name === pkg.name) {
      return true
    }
  }
  return false
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
a.disabled {
  color: #666;
  pointer-events: none;
}

.pkg-badge {
  border: 2px solid #08c;
  margin: 4px 4px 4px 0;
  display: inline-block;
}

.pkg-badge-content {
  padding: 4px 8px;
}

.pkg-badge-button {
  padding: 4px 8px;
  border-left: 2px solid #08c;
}

</style>
