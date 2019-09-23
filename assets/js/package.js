/* eslint-env browser */
import Chartist from 'chartist'
// support IE 11
import 'whatwg-fetch'

const ChartElement = document.querySelector('#series')
const url = ChartElement.dataset.url

fetch(url)
  .then(response => response.json()
  )
  .then(json => {
    const data = {
      labels: [],
      series: [[]]
    }

    if (json && json.packagePopularities) {
      json.packagePopularities.forEach((packagePopularity) => {
        if (packagePopularity.startMonth && packagePopularity.popularity) {
          data.labels.push(packagePopularity.startMonth)
          data.series[0].push(packagePopularity.popularity)
        }
      })
    }

    // Remove the spinner
    ChartElement.innerHTML = ''

    Chartist.Line(ChartElement, data, {
      showPoint: false,
      showArea: true,
      axisX: {
        showGrid: false,
        labelInterpolationFnc: value => value.toString().endsWith('01') && value.toString().slice(0, -2) % 2 === 0 ? value.toString().slice(0, -2) : null
      }
    }, [
      ['screen and (min-width: 576px)', {
        axisX: {
          labelInterpolationFnc: value => value.toString().endsWith('01') ? value.toString().slice(0, -2) : null
        }
      }]
    ])
  })
  .catch(e => {
    // Remove the spinner
    ChartElement.innerHTML = ''

    const error = document.createElement('div')
    error.classList.add('alert')
    error.classList.add('alert-danger')
    error.setAttribute('role', 'alert')
    error.innerText = e.toString()
    ChartElement.appendChild(error)
  })
