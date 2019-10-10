/* eslint-env browser */
export default (() => {
  const packageUrlTemplate = '/api/packages/{package}'
  const packageSeriesUrlTemplate = '/api/packages/{package}/series'
  const packagesUrl = '/api/packages'

  const fetchJson = url => fetch(url, {
    credentials: 'omit',
    headers: new Headers({ Accept: 'application/json' })
  }).then(response => response.json())

  return {
    fetchPackagePopularity: (pkg, options = {}) => {
      const { startMonth, endMonth } = options

      const packagePopularityQueryParameters = new URLSearchParams()
      startMonth && packagePopularityQueryParameters.set('startMonth', startMonth)
      endMonth && packagePopularityQueryParameters.set('endMonth', endMonth)
      packagePopularityQueryParameters.sort()
      const packagePopularityQueryString = packagePopularityQueryParameters.toString()

      return fetchJson(
        packageUrlTemplate.replace('{package}', pkg) +
        (packagePopularityQueryString ? '?' + packagePopularityQueryString : '')
      )
        .then(data => data.popularity)
    },
    fetchPackageSeries: (pkg, options = {}) => {
      const { startMonth, endMonth, limit } = options

      const packageSeriesQueryParameters = new URLSearchParams()
      startMonth && packageSeriesQueryParameters.set('startMonth', startMonth)
      endMonth && packageSeriesQueryParameters.set('endMonth', endMonth)
      limit >= 0 && packageSeriesQueryParameters.set('limit', limit)
      packageSeriesQueryParameters.sort()
      const packageSeriesQueryString = packageSeriesQueryParameters.toString()

      return fetchJson(
        packageSeriesUrlTemplate.replace('{package}', pkg) +
        (packageSeriesQueryString ? '?' + packageSeriesQueryString : '')
      )
    },
    fetchPackageList: options => {
      const { query, startMonth, endMonth, limit } = options

      const packagesQueryParameters = new URLSearchParams()
      query && packagesQueryParameters.set('query', query)
      startMonth && packagesQueryParameters.set('startMonth', startMonth)
      endMonth && packagesQueryParameters.set('endMonth', endMonth)
      limit >= 0 && packagesQueryParameters.set('limit', limit)
      packagesQueryParameters.sort()
      const packagesQueryString = packagesQueryParameters.toString()

      return fetchJson(packagesUrl + (packagesQueryString ? '?' + packagesQueryString : ''))
    }
  }
})()
