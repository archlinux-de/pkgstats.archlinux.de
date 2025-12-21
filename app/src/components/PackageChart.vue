<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error.length > 0">{{ error }}</div>
    <loading-spinner v-if="isFetching"></loading-spinner>
    <chart-js v-if="isFinished && error.length === 0" :data="chartData" :width="1280" :height="720" data-test="package-chart"></chart-js>
  </div>
</template>

<script setup>
import LoadingSpinner from './LoadingSpinner'
import ChartJs from './ChartJs'
import { useFetchPackagesSeries } from '../composables/useFetchPackagesSeries'
import { useConvertDataSeries } from '../composables/useConvertDataSeries'
import { computed } from 'vue'

const props = defineProps({
  packages: {
    type: Array,
    required: true,
    validator (value) {
      return value.length >= 1 && value.length <= 10
    }
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
  },
  filter: {
    type: String,
    default: 'All'
  }
})

const { data: fetchedData, isFetching, isFinished, error } = useFetchPackagesSeries(props.packages, { startMonth: props.startMonth, endMonth: props.endMonth, limit: props.limit })

const data = useConvertDataSeries(fetchedData, 'packagePopularities')

const chartData = computed(() => {
  if (props.filter === 'Top 5') {
    return {
      ...data.value,
      datasets: data.value.datasets.slice(0, 5)
    }
  }
  return data.value
})

</script>
