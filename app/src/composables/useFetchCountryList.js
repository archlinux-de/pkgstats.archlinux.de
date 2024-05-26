import { useDebounce } from '@vueuse/core'
import { computed } from 'vue'
import { useApiFetch } from './useApiFetch'

/**
 * @returns {Promise<any>}
 */
export const useFetchCountryList = () => {
  const url = useDebounce(computed(() => {
    const params = new URLSearchParams()
    params.set('offset', 0)
    params.set('limit', 0)
    params.sort()
    return '/api/countries?' + params.toString()
  }), 50)

  const result = useApiFetch(
    url,
    {
      initialData: { countryPopularities: [] }
    }
  ).get().json()

  return result
}
