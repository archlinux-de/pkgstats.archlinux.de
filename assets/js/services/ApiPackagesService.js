/* eslint-env browser */
export default (() => {
  const packageUrlTemplate = '/api/packages/{package}'
  const packageSeriesUrlTemplate = '/api/packages/{package}/series'
  const packagesUrl = '/api/packages'

  /**
   * @param {string} url
   * @returns {Promise<any>}
   */
  const fetchJson = url => fetch(url, {
    credentials: 'omit',
    headers: new Headers({ Accept: 'application/json' })
  }).then(response => response.json())

  /**
   * @param {string} path
   * @param {Object} options
   * @returns {string}
   */
  const createUrl = (path, options = {}) => {
    const url = new URL(path, location.toString())
    Object.entries(options).forEach(entry => {
      if (typeof entry[1] !== 'undefined' && entry[1].toString().length > 0) {
        url.searchParams.set(entry[0], entry[1])
      }
    })
    url.searchParams.sort()
    return url.toString()
  }

  return {
    /**
     * @param {string} pkg
     * @returns {Promise<number>}
     */
    fetchPackagePopularity (pkg) {
      return fetchJson(createUrl(
        packageUrlTemplate.replace('{package}', pkg)
      )).then(data => data.popularity)
    },

    /**
     * @param {string} pkg
     * @param {startMonth, endMonth, limit} options
     * @returns {Promise<any>}
     */
    fetchPackageSeries (pkg, options = {}) {
      return fetchJson(createUrl(
        packageSeriesUrlTemplate.replace('{package}', pkg),
        options
      ))
    },

    /**
     * @param {query, limit} options
     * @returns {Promise<any>}
     */
    fetchPackageList (options) {
      return fetchJson(createUrl(packagesUrl, options))
    }
  }
})()
