const createApiPackagesService = fetch => {
  const packageUrlTemplate = '/api/packages/:package'
  const packageSeriesUrlTemplate = '/api/packages/:package/series'
  const packagesUrl = '/api/packages'

  /**
   * @param {string} url
   * @returns {Promise<any>}
   */
  const fetchJson = url => {
    const controller = new AbortController()
    setTimeout(() => { controller.abort() }, 30000)

    return fetch(url, {
      credentials: 'omit',
      headers: { Accept: 'application/json' },
      signal: controller.signal
    }).then(response => {
      if (response.ok) {
        return response.json()
      }
      throw new Error(response.statusText)
    }).catch(error => {
      throw new Error(`Fetching URL "${url}" failed with "${error.message}"`)
    })
  }

  /**
   * @param {string} path
   * @param {Object} options
   * @returns {string}
   */
  const createUrl = (path, options = {}) => {
    const url = new URL(path, location.toString())
    Object.entries(options)
      .filter((entry) => typeof entry[1] !== 'undefined' && entry[1] !== null && entry[1].toString().length > 0)
      .forEach(entry => { url.searchParams.set(entry[0], entry[1]) })
    url.searchParams.sort()
    return url.toString()
  }

  return {
    /**
     * @param {string} pkg
     * @returns {Promise<any>}
     */
    fetchPackagePopularity (pkg) {
      return fetchJson(createUrl(packageUrlTemplate.replace(':package', pkg)))
        .then(data => {
          if (data.count === 0) {
            throw new Error(`No data found for package "${pkg}"`)
          }
          return data
        })
    },

    /**
     * @param {string} pkg
     * @param {startMonth, endMonth, limit} options
     * @returns {Promise<any>}
     */
    fetchPackageSeries (pkg, options = {}) {
      return fetchJson(createUrl(packageSeriesUrlTemplate.replace(':package', pkg), options))
        .then(data => {
          if (data.count === 0) {
            throw new Error(`No data found for package "${pkg}" with ${JSON.stringify(options)}`)
          }
          return data
        })
    },

    /**
     * @param {query, limit, offset} options
     * @returns {Promise<any>}
     */
    fetchPackageList (options) {
      return fetchJson(createUrl(packagesUrl, options))
    }
  }
}

export default createApiPackagesService
