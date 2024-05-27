<template>
  <div class="container" role="main">
    <h1 class="mb-4">{{ category }} statistics</h1>
    <ul class="nav nav-tabs mb-2">
      <li class="nav-item">
        <router-link :to="{name: 'fun-detail', params: {category: category, preset: 'current'}}" class="nav-link" exact>current</router-link>
      </li>
      <li class="nav-item" data-test="graph-chart-link">
        <router-link :to="{name: 'fun-detail', params: {category: category, preset: 'history'}}" class="nav-link" exact>history</router-link>
      </li>
    </ul>
    <packages-bar-chart v-if="showBarChart" :packages="pkgs"></packages-bar-chart>
    <package-chart v-if="showGraph" :limit="0" :packages="pkgs" :start-month="0"></package-chart>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import PackagesBarChart from '../components/PackagesBarChart'
import FunConfig from '../config/fun.json'
import PackageChart from '../components/PackageChart.vue'
import { ref, watch } from 'vue'
import { useRouteParams } from '@vueuse/router'

const category = useRouteParams('category')
const preset = useRouteParams('preset')

const pkgs = FunConfig[category.value]

const showBarChart = ref(true)
const showGraph = ref(false)
watch(preset, value => {
  switch (value) {
    case 'current':
      showBarChart.value = true
      showGraph.value = false
      break
    case 'history':
      showBarChart.value = false
      showGraph.value = true
      break
    default:
      showBarChart.value = true
      showGraph.value = false
  }
}, { immediate: true })

useHead({ title: category.value + ' statistics' })
</script>
