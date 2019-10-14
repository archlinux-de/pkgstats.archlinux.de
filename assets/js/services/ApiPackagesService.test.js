import createApiPackagesService from '@/js/services/ApiPackagesService'

describe('Testing fetchPackagePopularity', () => {
  it('Popularity can be fetched', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1, popularity: 42 })
    }))

    createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs')
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages/nodejs',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toBe(42)
      })
  })

  it('Fetching popularity of unknown package fails', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 0 })
    }))

    createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs')
      .catch(error => {
        expect(error.toString()).toBe('Error: No data found for package "nodejs"')
      })
  })

  it('Fetching popularity fails on server error', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: false,
      statusText: 'Server is down'
    }))

    createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs')
      .catch(error => {
        expect(error.toString())
          .toBe('Error: Fetching URL "http://localhost/api/packages/nodejs" failed with "Server is down"')
      })
  })
})

describe('Testing fetchPackageSeries', () => {
  it('Fetching package series', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', {
      startMonth: 202001,
      endMonth: 202012,
      limit: 100,
      offset: 10
    })
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages/nodejs/series?endMonth=202012&limit=100&offset=10&startMonth=202001',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toStrictEqual({ count: 1 })
      })
  })

  it('Fetching default package series', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', {
      startMonth: 0,
      endMonth: undefined,
      limit: 0
    })
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages/nodejs/series?limit=0&startMonth=0',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toStrictEqual({ count: 1 })
      })
  })

  it('Fetching complete package series', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', { startMonth: 0, limit: 0 })
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages/nodejs/series?limit=0&startMonth=0',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toStrictEqual({ count: 1 })
      })
  })

  it('Fetching empty results throws an error', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 0 })
    }))

    createApiPackagesService(fetchMock).fetchPackageSeries('nodejs')
      .catch(error => {
        expect(error.toString()).toBe('Error: No data found for package "nodejs" with {}')
      })
  })
})

describe('Testing fetchPackageList', () => {
  it('Fetching package list', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    createApiPackagesService(fetchMock).fetchPackageList({
      query: 'nodejs',
      startMonth: 202001,
      endMonth: 202012,
      limit: 100,
      offset: 10
    })
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages?endMonth=202012&limit=100&offset=10&query=nodejs&startMonth=202001',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toStrictEqual({ count: 1 })
      })
  })

  it('Fetching package list with invalid options', () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    createApiPackagesService(fetchMock).fetchPackageList({ foo: null })
      .then(result => {
        expect(fetchMock).toBeCalledWith(
          'http://localhost/api/packages',
          {
            'credentials': 'omit',
            'headers': { 'Accept': 'application/json' }
          }
        )
        expect(result).toStrictEqual({ count: 1 })
      })
  })
})
