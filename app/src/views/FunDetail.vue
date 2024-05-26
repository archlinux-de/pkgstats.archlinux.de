<template>
  <div class="container" role="main">
    <h1 class="mb-4">{{ categoryParameter }} statistics</h1>
      <packages-bar-chart :packages="pkgs"></packages-bar-chart>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import { useRoute } from 'vue-router'
import PackagesBarChart from '../components/PackagesBarChart'
import FunConfig from '../config/fun.json'

const category = useRoute().params.category
const pkgs = FunConfig[category]

useHead({ title: category + ' statistics' })
</script>
