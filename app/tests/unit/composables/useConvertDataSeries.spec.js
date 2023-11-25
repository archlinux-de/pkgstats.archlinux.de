import { useConvertDataSeries } from '../../../src/composables/useConvertDataSeries'
import { unref } from 'vue'

it('Converting an Array to a data series', () =>
  expect(unref(useConvertDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] }
  ], 'packagePopularities')))
    .toStrictEqual({
      labels: [201909],
      datasets: [
        { data: [64.01], label: 'nodejs' }
      ]
    })
)

it('Converting multiple Arrays to data series', () =>
  expect(unref(useConvertDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] },
    { packagePopularities: [{ name: 'php', popularity: 32.69, startMonth: 201909 }] }
  ], 'packagePopularities')))
    .toStrictEqual({
      labels: [201909],
      datasets: [
        { data: [64.01], label: 'nodejs' },
        { data: [32.69], label: 'php' }
      ]
    })
)

it('Converting multiple incomplete Arrays to a consistent data series', () =>
  expect(unref(useConvertDataSeries([
    { packagePopularities: [{ name: 'nodejs', popularity: 64.01, startMonth: 201909 }] },
    {
      packagePopularities: [
        { name: 'php', popularity: 32.69, startMonth: 201908 },
        { name: 'php', popularity: 12, startMonth: 201909 }
      ]
    }
  ], 'packagePopularities')))
    .toStrictEqual({
      labels: [201908, 201909],
      datasets: [
        { data: [null, 64.01], label: 'nodejs' },
        { data: [32.69, 12], label: 'php' }
      ]
    })
)

it('Converting an incomplete Array to an empty data series', () =>
  expect(unref(useConvertDataSeries([{ packagePopularities: [] }], 'packagePopularities')))
    .toStrictEqual({ labels: [], datasets: [] })
)
