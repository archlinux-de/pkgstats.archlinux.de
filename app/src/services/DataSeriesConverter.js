export default function convertToDataSeries (PackagepopularitiesArray) {
  const tempSeries = new Map()
  const tempLabels = new Set()

  PackagepopularitiesArray.filter(data => data).forEach(result => {
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
