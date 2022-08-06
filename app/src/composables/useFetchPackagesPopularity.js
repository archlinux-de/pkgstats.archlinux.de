import { ref, unref } from 'vue'
import { useFetchPackagePopularity } from './useFetchPackagePopularity'

const sortPackagesByPopularity = packagePopularities => packagePopularities.sort((a, b) => Math.sign(b.popularity - a.popularity))

/**
 * @param {array<string>} pkgs
 * @returns {Promise<any>}
 */
export const useFetchPackagesPopularity = (pkgs) => {
  const data = ref(unref(pkgs).map(pkg => ({ name: pkg, popularity: 0.0 })))
  const isFinished = ref(false)
  const isFetching = ref(true)
  const error = ref([])

  Promise.all(unref(pkgs).map(pkg => useFetchPackagePopularity(pkg)))
    .then(results => {
      data.value = sortPackagesByPopularity(results.map(result => unref(result.data)))
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
