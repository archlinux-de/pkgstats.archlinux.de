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
        <div class="mm-grid">
          <picture class="mm-overlapping">
            <source :srcset="barChartImageLight" media="(prefers-color-scheme: light)"/>
            <source :srcset="barChartImageDark"  media="(prefers-color-scheme: dark)"/>
            <img alt="Package Bar Charts" height="129" width="435" :src="barChartImageDark" class="border border-primary-subtle shadow shadow-3"/>
          </picture>
          <picture class="mm-underlying">
            <source :srcset="graphImageLight" media="(prefers-color-scheme: light)"/>
            <source :srcset="graphImageDark"  media="(prefers-color-scheme: dark)"/>
            <img alt="Package Graphs" height="210" width="400" :src="graphImageLight" class="border border-primary-subtle shadow shadow-3"/>
          </picture>
        </div>

      </div>
    </div>
    <div class="mt-4">Contributions are welcome. For more information please refer to the repository
      <a href="https://github.com/archlinux-de/pkgstats.archlinux.de/blob/main/README.md">README</a>
      .
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

<style scoped lang="scss">
.mm-grid {
  display: grid;
  grid-template-columns: 4fr 2fr 2fr 3fr 6fr;
}

.mm-overlapping {
  grid-row: 1 / span 2;
  grid-column: 1 / span 2;
  z-index: 2;
}

.mm-underlying {
  grid-row: 2 / span 4;
  grid-column: 2 / span 3;
}

.mm-underlying:hover {
  z-index:3;
}
</style>
