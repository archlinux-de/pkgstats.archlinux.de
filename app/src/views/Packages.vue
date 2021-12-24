<template>
  <main class="container" role="main">
    <h1 class="mb-4">Package statistics</h1>

    <div class="input-group mb-4">
      <span class="input-group-text" id="package-search-label">Package name</span>
      <input class="form-control" type="search"
             placeholder="e.g. pacman" aria-describedby="package-search-label"
             v-model="query">
    </div>

    <table class="table table-striped table-borderless table-sm" v-if="data.packagePopularities.length > 0">
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

    <loading-spinner v-if="isFetching"></loading-spinner>
    <div role="alert" class="alert alert-danger" v-if="error">{{ error }}</div>

    <div role="alert" v-if="isFinished && data.total === data.count" class="alert alert-info mb-4">
      {{ data.total }} packages found
    </div>

    <div class="d-flex justify-content-center mb-4"
         ref="loadMore" v-if="isFinished && !isFetching && data.count < data.total">
      <button class="btn btn-primary" @click="offset+=limit">Load more</button>
    </div>
  </main>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDebounce, useFetch, useIntersectionObserver } from '@vueuse/core'
import { useHead } from '@vueuse/head'
import { useRouteHash, useRouteQuery } from '@vueuse/router'
import LoadingSpinner from '../components/LoadingSpinner'

const query = useRouteQuery('query', useRouteHash('').value.replace(/^#query=/, ''))
const offset = ref(0)
const limit = ref(60)
const url = useDebounce(computed(() => {
  const params = new URLSearchParams()
  params.set('offset', offset.value)
  params.set('limit', limit.value)
  params.set('query', encodeURIComponent(query.value))
  params.sort()
  return '/api/packages?' + params.toString()
}), 50)
const loadMore = ref(null)

const { isFetching, isFinished, error, data } = useFetch(url, {
  refetch: true,
  initialData: {
    count: 0,
    lastCount: 0,
    total: 0,
    limit: 0,
    offset: 0,
    query: '',
    packagePopularities: []
  },
  credentials: 'omit',
  headers: { Accept: 'application/json' },
  afterFetch (ctx) {
    ctx.data.lastCount = ctx.data.count

    if (ctx.data.query === data.value.query && ctx.data.offset === data.value.offset + data.value.limit) {
      ctx.data.count += data.value.count
      ctx.data.packagePopularities = [...data.value.packagePopularities, ...ctx.data.packagePopularities]
    }

    return ctx
  }
}).json()

watch(() => query.value, (currentQuery, previousQuery) => {
  if (currentQuery !== previousQuery) {
    offset.value = 0
  }
})

const { stop } = useIntersectionObserver(loadMore, ([{ isIntersecting }]) => {
  if (!isIntersecting || isFetching.value || error.value || data.value.lastCount === 0 || data.value.count >= data.value.total) {
    return
  }

  offset.value += limit.value
}, { rootMargin: '0px 0px 300px 0px' })

onUnmounted(() => {
  stop()
})

useHead({
  title: 'Package statistics',
  link: [{ rel: 'canonical', href: window.location.orient + useRoute().path }],
  meta: [{
    name: 'robots',
    content: computed(() => (data.value.count === 0 || error.value ? 'noindex' : 'index') + ',follow')
  }]
})
</script>
