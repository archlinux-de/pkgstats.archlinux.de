/* eslint-env browser */
import Chartist from 'chartist'
import 'chartist-plugin-legend'
// support IE 11
import 'whatwg-fetch'

const ChartElement = document.querySelector('#series')
const urlTemplate = ChartElement.dataset.urlTemplate

let packages = location.hash
  .replace(/^#packages=/, '')
  .split(',')
  .filter(pkg => {
    return pkg.length > 0
  })

packages.sort()
// limit the number of line graphs
packages = packages.slice(0, 5)
location.hash = 'packages=' + packages.join(',')

const data = {
  labels: [],
  series: []
}

const tempSeries = []
packages.forEach((pkg) => {
  tempSeries.push([])
  data.series.push([])
})

Promise.all(packages.map(packageName => {
  const url = urlTemplate.replace('_package_', packageName)
  return fetch(url).then(response => response.json())
})).then(results => {
  const tempLabels = []
  results.forEach((result, index) => {
    if (result && result.packagePopularities) {
      result.packagePopularities.forEach((packagePopularity) => {
        if (packagePopularity.startMonth && packagePopularity.popularity) {
          tempLabels[packagePopularity.startMonth] = true
          tempSeries[index][packagePopularity.startMonth] = packagePopularity.popularity
        }
      })
    }
  })

  if (!tempLabels.length) {
    throw new Error('No package data found')
  }

  tempLabels.forEach((_, key) => {
    data.labels.push(key)
  })
  data.labels.sort()

  data.labels.forEach(label => {
    tempSeries.forEach((s, i) => {
      data.series[i].push(s[label] ? s[label] : null)
    })
  })

  // Remove the spinner
  ChartElement.innerHTML = ''

  Chartist.Line(ChartElement, data, {
    showPoint: false,
    showArea: packages.length < 4,
    chartPadding: {
      top: 24
    },
    axisX: {
      showGrid: false,
      labelInterpolationFnc: value => value.toString().endsWith('01') && value.toString().slice(0, -2) % 2 === 0 ? value.toString().slice(0, -2) : null
    },
    plugins: [
      Chartist.plugins.legend({
        legendNames: packages,
        clickable: false
      })
    ]
  }, [
    ['screen and (min-width: 576px)', {
      chartPadding: {
        top: 36
      },
      axisX: {
        labelInterpolationFnc: value => value.toString().endsWith('01') ? value.toString().slice(0, -2) : null
      }
    }],
    ['screen and (min-width: 768px)', {
      chartPadding: {
        top: 48
      }
    }]
  ])
}).catch(e => {
  // Remove the spinner
  ChartElement.innerHTML = ''

  const error = document.createElement('div')
  error.classList.add('alert')
  error.classList.add('alert-danger')
  error.setAttribute('role', 'alert')
  error.innerText = e.toString()
  ChartElement.appendChild(error)
})
