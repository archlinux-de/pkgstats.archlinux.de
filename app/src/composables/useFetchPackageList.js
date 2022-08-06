import { useDebounce } from '@vueuse/core'
import { computed, watch } from 'vue'
import { useApiFetch } from './useApiFetch'

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
