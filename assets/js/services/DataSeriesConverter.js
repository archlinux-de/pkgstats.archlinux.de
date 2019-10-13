export default function convertToDataSeries (PackagepopularitiesArray) {
  const tempSeries = new Map()
  const tempLabels = new Set()

  PackagepopularitiesArray.forEach(result => {
    result.packagePopularities.forEach((packagePopularity) => {
      tempLabels.add(packagePopularity.startMonth)
      let tempData
      if (tempSeries.has(packagePopularity.name)) {
        tempData = tempSeries.get(packagePopularity.name)
      } else {
        tempData = new Map()
        tempSeries.set(packagePopularity.name, tempData)
      }
      tempData.set(packagePopularity.startMonth, packagePopularity.popularity)
    })
  })

  const data = {
    labels: Array.from(tempLabels).sort(),
    series: []
  }

  tempSeries.forEach((series, name) => {
    data.series.push({
      name: name,
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
