<template>
  <div class="container" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <div class="row row-cols-1 row-cols-lg-2">
      <div class="mb-2 mb-lg-4">
        <h2>Categories</h2>
        <div :key="title" v-for="(pkgs, title) in FunConfig" :data-test="title">
          <router-link data-test="fun-detail-link" :to="{ name: 'fun-detail', params: {category: title, preset: 'current'} }">
            {{ title }}
          </router-link>
        </div>
      </div>
      <div class="col">
        <h2>Preview</h2>
        <p>Chose a group of pre-selected packages...</p>
        <picture>
          <source :srcset="barChartImageLight" media="(prefers-color-scheme: light)"/>
          <source :srcset="barChartImageDark"  media="(prefers-color-scheme: dark)"/>
          <img alt="Package Bar Charts" height="129" width="435" :src="barChartImageDark" class="d-inline-block align-text-top"/>
        </picture>
        <div class="mt-2 mb-2">... and assess the current popularity or the development over time.</div>
        <div>
          <picture>
            <source :srcset="graphImageLight" media="(prefers-color-scheme: light)"/>
            <source :srcset="graphImageDark"  media="(prefers-color-scheme: dark)"/>
            <img alt="Package Graphs" height="244" width="434" :src="graphImageLight" class="d-inline-block align-text-top only-on-light"/>
          </picture>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHead } from '@vueuse/head'
import FunConfig from '../config/fun.json'
import graphImageDark from '../assets/images/fun_graph_darkMode.webp'
import graphImageLight from '../assets/images/fun_graph_lightMode.webp'
import barChartImageDark from '../assets/images/fun_barChart_darkMode.webp'
import barChartImageLight from '../assets/images/fun_barChart_lightMode.webp'

useHead({ title: 'Fun statistics' })
</script>
