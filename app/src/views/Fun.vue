<template>
  <div class="container" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <div :key="title" v-for="(pkgs, title) in FunConfig">
      <h2>
        <router-link :to="{ name: 'fun-detail', params: {category: title} }">
          {{ title }}
        </router-link>
      </h2>
    </div>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import FunConfig from '../config/fun.json'

useHead({ title: 'Fun statistics' })
</script>
