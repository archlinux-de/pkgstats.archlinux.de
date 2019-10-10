<template>
  <div class="ct-chart ct-minor-seventh">
    <div class="spinner-container" v-if="loading">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
    </div>
  </div>
</template>

<script>
  import Chartist from 'chartist'
  import 'chartist-plugin-legend'
  import ApiPackagesService from '../services/ApiPackagesService'
  import { convertToDataSeries } from '../services/DataSeriesConverter'

  export default {
    name: 'PackageChart',
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
        data: []
      }
    },
    watch: {
      packages: function () {
        this.fetchData()
      },
      data: function () {
        this.drawChart()
      }
    },
    methods: {
      fetchData: function () {
        this.loading = true
        Promise.all(this.packages.map(pkg => ApiPackagesService.fetchPackageSeries(pkg, {
          startMonth: this.startMonth,
          endMonth: this.endMonth,
          limit: this.limit
        })))
          .then(dataArray => {
            dataArray.forEach(data => {
              if (!data.count) {
                throw new Error('No package data found')
              }
            })
            this.data = convertToDataSeries(dataArray)
          })
          .catch(error => console.error(error))
          .finally(() => {this.loading = false})
      },
      drawChart: function () {
        Chartist.Line(this.$el, this.data, {
          showPoint: false,
          showArea: this.data.series.length < 4,
          chartPadding: {
            top: 24,
            bottom: 12
          },
          axisX: {
            showGrid: false,
            labelInterpolationFnc: value => value.toString().endsWith('01') && value.toString().slice(0, -2) % 2 === 0 ? value.toString().slice(0, -2) : null
          },
          plugins: this.data.series.length > 1 ? [
            Chartist.plugins.legend({
              clickable: false
            })
          ] : []
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
      }
    },
    mounted () {
      this.fetchData()
    }
  }
</script>
