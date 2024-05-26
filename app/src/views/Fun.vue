<template>
  <div class="container" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <p>Chose a group of pre-selected packages to see current popularities
    and the development over time.</p>
    <div class="mb-2 mb-lg-4">
      <div :key="title" v-for="(pkgs, title) in FunConfig">
        <router-link :to="{ name: 'fun-detail', params: {category: title} }">
          {{ title }}
        </router-link>
      </div>
    </div>

    <h2>Preview</h2>
    <div class="row row-cols-1 row-cols-lg-2">
      <div class="col">
        <packages-bar-chart :packages="examplePkgs"></packages-bar-chart>
      </div>
      <div class="col">
        <package-chart :limit="0" :packages="examplePkgs" :start-month="0"></package-chart>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import FunConfig from '../config/fun.json'
import PackagesBarChart from '../components/PackagesBarChart.vue'
import PackageChart from '../components/PackageChart.vue'

useHead({ title: 'Fun statistics' })
const examplePkgs = FunConfig.Browsers
</script>
