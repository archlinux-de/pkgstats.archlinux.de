import { ref, unref, watch } from 'vue'
import { useFetchPackagePopularity } from './useFetchPackagePopularity'

const sortPackagesByPopularity = (packagePopularities) =>
  packagePopularities.sort((a, b) => Math.sign(b.popularity - a.popularity))

/**
 * @param {ref<array>} pkgs
 * @returns {Promise<any>}
 */
export const useFetchPackagesPopularity = (pkgs) => {
  const data = ref(unref(pkgs).map((pkg) => ({ name: pkg, popularity: 0.0 })))
  const isFinished = ref(false)
  const isFetching = ref(true)
  const error = ref([])

  const fetchPkgData = () => Promise.all(
    unref(pkgs).map((pkg) => useFetchPackagePopularity(pkg))
  )
    .then((results) => {
      data.value = sortPackagesByPopularity(
        results.map((result) => unref(result.data))
      )
      error.value = results
        .map((result) => unref(result.error))
        .filter((error) => error)
      isFinished.value = true
    })
    .catch((e) => {
      error.value.push(e.toString())
    })
    .finally(() => {
      isFetching.value = false
    })

  watch(pkgs, fetchPkgData, { immediate: true })

  return {
    data,
    isFetching,
    isFinished,
    error
  }
}
