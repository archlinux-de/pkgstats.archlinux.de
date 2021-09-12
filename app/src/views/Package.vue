<template>
  <div class="container" role="main">
    <h1 class="mb-3">{{ packageParameter }}</h1>
    <p class="mb-3">Relative usage of {{ packageParameter }}</p>
    <package-chart :limit="0" :packages="[packageParameter]" :start-month="0"></package-chart>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import { useRoute } from 'vue-router'
import PackageChart from '../components/PackageChart'

const packageParameter = useRoute().params.package

useHead({ title: packageParameter })
</script>
