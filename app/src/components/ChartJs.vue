<template>
    <canvas ref="canvas" :width="width" :height="height"></canvas>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch, toRefs } from 'vue'
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

const props = defineProps({
  width: {
    type: Number,
    required: true
  },
  height: {
    type: Number,
    required: true
  },
  data: {
    type: Object,
    required: true,
    validator (value) {
      return Array.isArray(value.labels) && Array.isArray(value.datasets)
    }
  }
})

const { width, height, data } = toRefs(props)

const canvas = ref()

const renderYearMonth = yearMonth => {
  const yearMonthString = yearMonth.toString()
  const year = yearMonthString.substring(0, 4)
  const month = yearMonthString.substring(4, 6)
  return `${year}-${month}`
}

const testRenderPlugin = {
  id: 'testRenderPlugin',
  beforeRender: function (chart) {
    chart.canvas.dataset.testRendered = 'false'
  },
  afterRender: function (chart) {
    chart.canvas.dataset.testRendered = 'true'
  }
}

const drawChart = (canvas, data) => {
  const colors = ['#08c', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#d63384', '#fd7e14', '#333', '#6f42c1', '#adb5bd']
  const textColor = '#333'

  Chart.register(
    LineElement,
    PointElement,
    LineController,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
    testRenderPlugin
  )

  return new Chart(canvas, {
    type: 'line',
    data,
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
  const chart = drawChart(canvas.value, data.value)

  const unwatch = watch(data, () => {
    chart.data = data.value
    chart.update()
  })

  onBeforeUnmount(() => {
    unwatch()
    chart.destroy()
  })
})
</script>
