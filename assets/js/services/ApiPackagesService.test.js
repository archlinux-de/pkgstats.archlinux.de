import createApiPackagesService from '@/js/services/ApiPackagesService'

describe('Testing fetchPackagePopularity', () => {
  it('Popularity can be fetched', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1, popularity: 42 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs'))
      .toEqual({ 'count': 1, 'popularity': 42 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages/nodejs',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })

  it('Fetching popularity of unknown package fails', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 0 })
    }))

    expect.assertions(1)
    await createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs')
      .catch(error => {
        expect(error.toString()).toContain('nodejs')
      })
  })

  it('Fetching popularity fails on server error', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: false,
      statusText: 'Server is down'
    }))

    expect.assertions(1)
    await createApiPackagesService(fetchMock).fetchPackagePopularity('nodejs')
      .catch(error => { expect(error.toString()).toBeTruthy() })
  })
})

describe('Testing fetchPackageSeries', () => {
  it('Fetching package series', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', {
      startMonth: 202001,
      endMonth: 202012,
      limit: 100,
      offset: 10
    })).toStrictEqual({ count: 1 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages/nodejs/series?endMonth=202012&limit=100&offset=10&startMonth=202001',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })

  it('Fetching default package series', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', {
      startMonth: 0,
      endMonth: undefined,
      limit: 0
    })).toStrictEqual({ count: 1 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages/nodejs/series?limit=0&startMonth=0',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })

  it('Fetching complete package series', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackageSeries('nodejs', { startMonth: 0, limit: 0 }))
      .toStrictEqual({ count: 1 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages/nodejs/series?limit=0&startMonth=0',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })

  it('Fetching empty results throws an error', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 0 })
    }))

    expect.assertions(1)
    await createApiPackagesService(fetchMock).fetchPackageSeries('nodejs')
      .catch(error => {
        expect(error.toString()).toBe('Error: No data found for package "nodejs" with {}')
      })
  })
})

describe('Testing fetchPackageList', () => {
  it('Fetching package list', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackageList({
      query: 'nodejs',
      startMonth: 202001,
      endMonth: 202012,
      limit: 100,
      offset: 10
    })).toStrictEqual({ count: 1 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages?endMonth=202012&limit=100&offset=10&query=nodejs&startMonth=202001',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })

  it('Fetching package list with invalid options', async () => {
    const fetchMock = jest.fn().mockReturnValue(Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ count: 1 })
    }))

    expect(await createApiPackagesService(fetchMock).fetchPackageList({ foo: null })).toStrictEqual({ count: 1 })

    expect(fetchMock).toBeCalledWith(
      'http://localhost/api/packages',
      {
        'credentials': 'omit',
        'headers': { 'Accept': 'application/json' }
      }
    )
  })
})
