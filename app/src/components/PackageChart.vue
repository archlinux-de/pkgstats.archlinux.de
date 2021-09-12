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

<script>
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

export default {
  inject: ['apiPackagesService', 'convertToDataSeries'],
  props: {
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
  },
  data () {
    return {
      loading: true,
      data: {
        labels: [],
        datasets: []
      },
      errors: [],
      chart: null
    }
  },
  components: {
    LoadingSpinner
  },
  watch: {
    packages: function () {
      this.fetchData()
    },
    data: function () {
      if (this.data.datasets.length > 0) {
        this.drawChart()
      }
    }
  },
  methods: {
    fetchData () {
      if (this.packages.length < 1) {
        this.loading = false
        this.errors.push('No packages defined')
        return
      }
      if (this.packages.length > 10) {
        this.loading = false
        this.errors.push('Too many packages defined')
        return
      }

      this.loading = true
      Promise.all(this.packages.map(pkg => this.apiPackagesService.fetchPackageSeries(pkg,
        {
          startMonth: this.startMonth,
          endMonth: this.endMonth,
          limit: this.limit
        }).catch(error => {
        this.errors.push(error)
      })
      ))
        .then(dataArray => {
          this.data = this.convertToDataSeries(dataArray)
        })
        .catch(error => {
          this.errors.push(error)
        })
        .finally(() => {
          this.loading = false
        })
    },
    drawChart () {
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

      const ctx = this.$el.querySelector('#package-chart')
      this.chart = new Chart(ctx, {
        type: 'line',
        data: this.data,
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
  },
  mounted () {
    this.fetchData()
  }
}
</script>
