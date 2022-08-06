<template>
  <main class="container" role="main">
    <h1 class="mb-4">Package statistics</h1>

    <div class="input-group mb-4">
      <span class="input-group-text" id="package-search-label">Package name</span>
      <input class="form-control" type="search"
             placeholder="e.g. pacman" aria-describedby="package-search-label"
             v-model="query">
    </div>

    <div v-if="data.packagePopularities && data.packagePopularities.length > 0">
      <table class="table table-striped table-borderless table-sm">
        <thead>
        <tr>
          <th scope="col">Package</th>
          <th scope="col">Popularity</th>
        </tr>
        </thead>
        <tbody>
        <tr :key="id" v-for="(pkg, id) in data.packagePopularities">
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
