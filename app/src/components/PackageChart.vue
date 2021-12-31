<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="errors.length > 0">
      <ul :key="id" class="list-group list-unstyled" v-for="(error, id) in errors">
        <li>{{ error }}</li>
      </ul>
    </div>
    <loading-spinner v-if="loading"></loading-spinner>
    <canvas id="package-chart" width="1280" height="720"></canvas>
  </div>
</template>

<script setup>
import { toRefs, ref, inject, onMounted, reactive, watch, onBeforeUnmount } from 'vue'
import {
  Chart,
  LineElement,
  PointElement,
  LineController,
  CategoryScale,
  LinearScale,
  Legend,
  Tooltip
} from 'chart.js'
import LoadingSpinner from './LoadingSpinner'

const apiPackagesService = inject('apiPackagesService')
const convertToDataSeries = inject('convertToDataSeries')

const props = defineProps({
  packages: {
    type: Array,
    required: true
  },
  startMonth: {
    type: Number,
    required: false
  },
  endMonth: {
    type: Number,
    required: false
  },
  limit: {
    type: Number,
    required: false
  }
})
const { packages, startMonth, endMonth, limit } = toRefs(props)

const loading = ref(true)
const errors = ref([])
const data = reactive({
  labels: [],
  datasets: []
})
let chart = null

const fetchData = () => {
  if (packages.value.length < 1) {
    loading.value = false
    errors.value.push('No packages defined')
    return
  }
  if (packages.value.length > 10) {
    loading.value = false
    errors.value.push('Too many packages defined')
    return
  }

  loading.value = true
  Promise.all(packages.value.map(pkg => apiPackagesService.fetchPackageSeries(pkg,
    {
      startMonth: startMonth.value,
      endMonth: endMonth.value,
      limit: limit.value
    }).catch(error => {
    errors.value.push(error)
  })
  ))
    .then(dataArray => {
      data.value = convertToDataSeries(dataArray)
    })
    .catch(error => {
      errors.value.push(error)
    })
    .finally(() => {
      loading.value = false
    })
}

const drawChart = () => {
  const renderYearMonth = yearMonth => {
    const yearMonthString = yearMonth.toString()
    const year = yearMonthString.substr(0, 4)
    const month = yearMonthString.substr(4, 2)
    return `${year}-${month}`
  }

  const colors = ['#08c', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#d63384', '#fd7e14', '#333', '#6f42c1', '#adb5bd']
  const textColor = '#333'

  Chart.register(
    LineElement,
    PointElement,
    LineController,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip
  )

  chart = new Chart('package-chart', {
    type: 'line',
    data: data.value,
    options: {
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        tooltip: {
          displayColors: false,
          itemSort: (a, b) => b.raw - a.raw,
          callbacks: {
            title: (title) => renderYearMonth(title[0].label)
          }
        },
        legend: {
          labels: {
            color: textColor
          }
        }
      },
      normalized: true,
      scales: {
        x: {
          ticks: {
            callback: function (val) {
              const yearMonth = this.getLabelForValue(val)
              return renderYearMonth(yearMonth)
            },
            color: textColor,
            autoSkipPadding: 30
          },
          grid: {
            display: false
          }
        },
        y: {
          type: 'linear',
          min: 0,
          grid: {
            borderDash: [1, 2]
          },
          ticks: {
            color: textColor
          }
        }
      },
      elements: {
        line: {
          borderColor: colors
        },
        point: {
          radius: 0,
          hoverRadius: 4,
          hoverBackgroundColor: textColor
        }
      }
    }
  })
}

onMounted(() => {
  fetchData()
})

onBeforeUnmount(() => {
  chart.destroy()
})

watch(() => data.value, () => {
  if (data.value.datasets.length > 0) {
    drawChart()
  }
})
</script>
