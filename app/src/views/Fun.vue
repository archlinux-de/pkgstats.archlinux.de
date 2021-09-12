<template>
  <div class="container" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <div :key="title" v-for="(pkgs, title) in FunConfig">
      <h2>
        <router-link :to="{ name: 'compare', hash: '#packages=' + Array.from(pkgs).sort() }">{{ title }}</router-link>
      </h2>
      <packages-bar-chart :packages="pkgs"></packages-bar-chart>
    </div>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import FunConfig from '../config/fun.json'
import PackagesBarChart from '../components/PackagesBarChart'

useHead({ title: 'Fun statistics' })
</script>
