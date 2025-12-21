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
    <package-chart v-if="showGraph" :limit="0" :packages="pkgs" :start-month="0" :filter="filter"></package-chart>

    <div v-if="route.path.endsWith('/history')">
      <h2 class="mt-4">Filters</h2>
      <div class="btn-group" role="group" aria-label="Filter chart data">
        <input type="radio" class="btn-check" id="packagesRadioAll" value="All" v-model="filter" autocomplete="off">
        <label class="btn" :class="filter === 'All' ? 'btn-primary' : 'btn-outline-primary'" for="packagesRadioAll">
          All
        </label>

        <input type="radio" class="btn-check" id="packagesRadioTop5" value="Top 5" v-model="filter" autocomplete="off">
        <label class="btn" :class="filter === 'Top 5' ? 'btn-primary' : 'btn-outline-primary'" for="packagesRadioTop5">
          Top 5
        </label>
      </div>
    </div>

  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import PackagesBarChart from '../components/PackagesBarChart'
import FunConfig from '../config/fun.json'
import PackageChart from '../components/PackageChart.vue'
import { ref, watch } from 'vue'
import { useRouteParams } from '@vueuse/router'
import { useRoute } from 'vue-router'

const category = useRouteParams('category')
const preset = useRouteParams('preset')
const route = useRoute()
const filter = ref('All')

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
