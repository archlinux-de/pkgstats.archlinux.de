import { computed, unref } from 'vue'
import { useApiFetch } from './useApiFetch'

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
