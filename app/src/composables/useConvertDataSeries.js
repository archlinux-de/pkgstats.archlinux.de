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

  // sort packages by current popularity (desc)
  const sortedTempSeries = Array.from(tempSeries.entries()).sort((a, b) => {
    const lastLabel = data.labels[data.labels.length - 1]
    const popA = a[1].get(lastLabel) || 0
    const popB = b[1].get(lastLabel) || 0
    return popB - popA
  })

  sortedTempSeries.forEach(([name, series]) => {
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
