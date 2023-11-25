<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error.length > 0">{{ error }}</div>
    <loading-spinner v-if="isFetching"></loading-spinner>
    <chart-js v-if="isFinished && error.length === 0" :data="data" :width="1280" :height="720" data-test="system-architecture-chart"></chart-js>
  </div>
</template>

<script setup>
import LoadingSpinner from './LoadingSpinner'
import ChartJs from './ChartJs'
import { useFetchSystemArchitecturesSeries } from '../composables/useFetchSystemArchitecturesSeries'
import { useConvertDataSeries } from '../composables/useConvertDataSeries'

const props = defineProps({
  systemArchitectures: {
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
  }
})

const { data: fetchedData, isFetching, isFinished, error } = useFetchSystemArchitecturesSeries(props.systemArchitectures, { startMonth: props.startMonth, endMonth: props.endMonth, limit: props.limit })

const data = useConvertDataSeries(fetchedData, 'systemArchitecturePopularities')
</script>
