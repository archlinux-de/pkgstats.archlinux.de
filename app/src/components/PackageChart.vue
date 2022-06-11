<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error">{{ error }}</div>
    <loading-spinner v-if="isFetching"></loading-spinner>
    <chart-js v-if="isFinished" :data="data" :width="1280" :height="720" data-test="package-chart"></chart-js>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'

import LoadingSpinner from './LoadingSpinner'
import { useFetchPackagesSeries } from '../composables/useApiFetch'
import ChartJs from './ChartJs'

const convertToDataSeries = inject('convertToDataSeries')

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
  }
})

/*
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
  */

const { data: fetchedData, isFetching, isFinished, error } = useFetchPackagesSeries(props.packages, { startMonth: props.startMonth, endMonth: props.endMonth, limit: props.limit })

const data = computed(() => convertToDataSeries(fetchedData.value))
</script>
