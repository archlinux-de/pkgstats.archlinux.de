import { convertToDataSeries } from '@/js/services/DataSeriesConverter'

it('An empty Array is converted to an empty series', () =>
  expect(convertToDataSeries([]))
    .toStrictEqual({ 'labels': [], 'series': [] })
)
