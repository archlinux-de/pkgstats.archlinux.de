import { ref, unref, computed } from 'vue'
import { useApiFetch } from './useApiFetch'

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
  const error = ref([])

  Promise.all(unref(pkgs).map(pkg => useFetchPackageSeries(pkg, options)))
    .then(results => {
      data.value = results.map(result => unref(result.data))
      error.value = results.map(result => unref(result.error)).filter(error => error)
      isFinished.value = true
    })
    .catch(e => {
      error.value.push(e.toString())
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
