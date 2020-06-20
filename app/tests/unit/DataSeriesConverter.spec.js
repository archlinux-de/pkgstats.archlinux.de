import convertToDataSeries from '@/services/DataSeriesConverter'

it('Converting an Array to a data series', () =>
  expect(convertToDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] }
  ]))
    .toStrictEqual({
      labels: [201909],
      series: [
        { data: [64.01], name: 'nodejs' }
      ]
    })
)

it('Converting multiple Arrays to data series', () =>
  expect(convertToDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] },
    { packagePopularities: [{ name: 'php', popularity: 32.69, startMonth: 201909 }] }
  ]))
    .toStrictEqual({
      labels: [201909],
      series: [
        { data: [64.01], name: 'nodejs' },
        { data: [32.69], name: 'php' }
      ]
    })
)

it('Converting multiple incomplete Arrays to a consistent data series', () =>
  expect(convertToDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] },
    {
      packagePopularities: [
        { name: 'php', popularity: 32.69, startMonth: 201908 },
        { name: 'php', popularity: 12, startMonth: 201909 }
      ]
    }
  ]))
    .toStrictEqual({
      labels: [201908, 201909],
      series: [
        { data: [null, 64.01], name: 'nodejs' },
        { data: [32.69, 12], name: 'php' }
      ]
    })
)

it('Converting an incomplete Array to an empty data series', () =>
  expect(convertToDataSeries([{ packagePopularities: [] }]))
    .toStrictEqual({ labels: [], series: [] })
)
