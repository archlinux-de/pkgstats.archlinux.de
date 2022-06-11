import { createFetch, useDebounce } from '@vueuse/core'
import { computed, ref, unref, watch } from 'vue'

const useApiFetch = createFetch({
  options: {
    timeout: 30000
  },
  fetchOptions: {
    headers: { Accept: 'application/json' },
    credentials: 'omit'
  }
})

/**
 * @param {string} path
 * @param {Object} options
 * @returns {string}
 */
const createUrl = (path, options = {}) => {
  const url = new URL(path, window.location.toString())
  Object.entries(options)
    .filter((entry) => typeof entry[1] !== 'undefined' && entry[1] !== null && entry[1].toString().length > 0)
    .forEach(entry => { url.searchParams.set(entry[0], entry[1]) })
  url.searchParams.sort()
  return url.toString()
}

/**
 * @param {string} pkg
 * @returns {Promise<any>}
 */
export const useFetchPackagePopularity = (pkg) => {
  const url = computed(() => `/api/packages/${unref(pkg)}`)

  return useApiFetch(
    url,
    {
      initialData: {
        name: unref(pkg),
        popularity: 0.0
      },
      refetch: true
    }
  ).get().json()
}

/**
 * @param {ref<string>} query
 * @param {ref<int>} offset
 * @param {ref<int>} limit
 * @returns {Promise<any>}
 */
export const useFetchPackageList = (query, offset, limit) => {
  const url = useDebounce(computed(() => {
    const params = new URLSearchParams()
    params.set('offset', offset.value)
    params.set('limit', limit.value)
    params.set('query', encodeURIComponent(query.value))
    params.sort()
    return '/api/packages?' + params.toString()
  }), 50)

  watch(() => query.value, (currentQuery, previousQuery) => {
    if (currentQuery !== previousQuery) {
      offset.value = 0
    }
  })

  const result = useApiFetch(
    url,
    {
      initialData: { packagePopularities: [] },
      refetch: true,
      afterFetch: (ctx) => {
        console.log('var')
        if (result.data.value.offset < ctx.data.offset) {
          ctx.data.packagePopularities = [...result.data.value.packagePopularities, ...ctx.data.packagePopularities]
        }
        ctx.data.count = ctx.data.packagePopularities.length
        ctx.data.offset = 0
        ctx.data.limit = ctx.data.count

        return ctx
      }
    }
  ).get().json()

  return result
}

const sortPackagesByPopularity = packagePopularities => packagePopularities.sort((a, b) => Math.sign(b.popularity - a.popularity))

/**
 * @param {array<string>} pkgs
 * @returns {Promise<any>}
 */
export const useFetchPackagesPopularity = (pkgs) => {
  const data = ref(unref(pkgs).map(pkg => ({ name: pkg, popularity: 0.0 })))
  const isFinished = ref(false)
  const isFetching = ref(true)
  const error = ref(null)

  Promise.all(unref(pkgs).map(pkg => useFetchPackagePopularity(pkg)))
    .then(results => {
      data.value = sortPackagesByPopularity(results.map(result => unref(result.data)))
      isFinished.value = true
    })
    .catch(e => {
      error.value = e.toString()
    })
    .finally(() => {
      isFetching.value = false
    })

  return {
    data,
    isFetching,
    isFinished,
    error
  }
}

/**
 * @param {string} pkg
 * @param {startMonth, endMonth, limit} options
 * @returns {Promise<any>}
 */
const useFetchPackageSeries = (pkg, options = {}) => {
  const url = computed(() => createUrl(`/api/packages/${unref(pkg)}/series`, options))

  return useApiFetch(
    url,
    {
      initialData: { packagePopularities: [] },
      refetch: true
    }
  ).get().json()
}

/**
 * @param {array<string>} pkgs
 * @param {startMonth, endMonth, limit} options
 * @returns {Promise<any>}
 */
export const useFetchPackagesSeries = (pkgs, options) => {
  const data = ref([])
  const isFinished = ref(false)
  const isFetching = ref(true)
  const error = ref(null)

  Promise.all(unref(pkgs).map(pkg => useFetchPackageSeries(pkg, options)))
    .then(results => {
      data.value = results.map(result => unref(result.data))
      isFinished.value = true
    })
    .catch(e => {
      error.value = e.toString()
    })
    .finally(() => {
      isFetching.value = false
    })

  return {
    data,
    isFetching,
    isFinished,
    error
  }
}
