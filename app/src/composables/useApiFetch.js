import { createFetch } from '@vueuse/core'

export const useApiFetch = createFetch({
  options: {
    timeout: 30000
  },
  fetchOptions: {
    headers: { Accept: 'application/json' },
    credentials: 'omit'
  }
})
