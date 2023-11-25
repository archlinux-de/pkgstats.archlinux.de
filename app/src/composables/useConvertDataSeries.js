import { computed, unref } from 'vue'

/**
 * @param {Object[]} PopularitiesArray
 * @param {string} pupularitiesKey
 * @returns {{datasets: Object[], labels: string[]}}
 */
const convertToDataSeries = (PopularitiesArray, pupularitiesKey) => {
  const tempSeries = new Map()
  const tempLabels = new Set()

  PopularitiesArray.filter(data => data).forEach(result => {
    if (!Array.isArray(result[pupularitiesKey])) {
      return
    }

    result[pupularitiesKey].forEach((popularity) => {
      tempLabels.add(popularity.startMonth)
      let tempData
      if (tempSeries.has(popularity.name)) {
        tempData = tempSeries.get(popularity.name)
      } else {
        tempData = new Map()
        tempSeries.set(popularity.name, tempData)
      }
      tempData.set(popularity.startMonth, popularity.popularity)
    })
  })

  const data = {
    labels: Array.from(tempLabels).sort(),
    datasets: []
  }

  tempSeries.forEach((series, name) => {
    data.datasets.push({
      label: name,
      data: (() => {
        const result = []
        data.labels.forEach(label => {
          if (series.has(label)) {
            result.push(series.get(label))
          } else {
            result.push(null)
          }
        })
        return result
      })()
    })
  })

  return data
}

export const useConvertDataSeries = (PopularitiesArray, pupularitiesKey) => computed(() => convertToDataSeries(unref(PopularitiesArray), unref(pupularitiesKey)))
