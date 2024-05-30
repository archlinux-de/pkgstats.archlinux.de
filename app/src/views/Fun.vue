<template>
  <div class="container" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <p>Chose a category to compare popularities and see the development over time.</p>
    <div class="row row-cols-1 row-cols-lg-2 mb-2 mb-lg-4">
      <div>
      <h2>Categories</h2>
        <div :key="title" v-for="(pkgs, title) in FunConfig" :data-test="title">
          <router-link data-test="fun-detail-link" :to="{ name: 'fun-detail', params: {category: title, preset: 'current'} }">
            {{ title }}
          </router-link>
        </div>
      </div>
      <div class="col d-flex align-items-center">
        <div class="chart-preview-grid">
          <picture class="chart-preview-overlapping">
            <source :srcset="barChartImageLight" media="(prefers-color-scheme: light)"/>
            <source :srcset="barChartImageDark"  media="(prefers-color-scheme: dark)"/>
            <img loading="lazy" alt="Package Bar Charts" height="96" width="353" :src="barChartImageDark" class="border border-primary-subtle shadow shadow-3"/>
          </picture>
          <picture class="chart-preview-underlying">
            <source :srcset="graphImageLight" media="(prefers-color-scheme: light)"/>
            <source :srcset="graphImageDark"  media="(prefers-color-scheme: dark)"/>
            <img loading="lazy" alt="Package Graphs" height="150" width="340" :src="graphImageLight" class="border border-primary-subtle shadow shadow-3"/>
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
.chart-preview-grid {
  display: none;
  /* stylelint-disable-next-line plugin/no-unsupported-browser-features */
  @media (width >= 992px) {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
  }
}

.chart-preview-overlapping {
  grid-row: 1 / span 2;
  grid-column: 1 / span 3;
  z-index: 2;
}

.chart-preview-underlying {
  grid-row: 2 / span 4;
  grid-column: 2 / span 3;
}
</style>
