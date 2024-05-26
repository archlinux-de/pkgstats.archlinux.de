<template>
  <div class="container" role="main">
    <h1 class="mb-4">{{ category }} statistics</h1>
    <packages-bar-chart :packages="pkgs"></packages-bar-chart>
    <package-chart :limit="0" :packages="pkgs" :start-month="0"></package-chart>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import { useRoute } from 'vue-router'
import PackagesBarChart from '../components/PackagesBarChart'
import FunConfig from '../config/fun.json'
import PackageChart from "../components/PackageChart.vue";

const category = useRoute().params.category
const pkgs = FunConfig[category]

useHead({ title: category + ' statistics' })
</script>
