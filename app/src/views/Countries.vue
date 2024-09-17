<template>
  <div class="container" role="main">
    <h1 class="mb-4">Countries</h1>
    Explore the distribution of the submitted package statistics.
    <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
    <div id="countries-map"></div>
  </div>
</template>

<script setup>
import { onMounted, watch } from 'vue'
import { useHead } from '@vueuse/head'
import SvgMap from 'svgmap'
import { useFetchCountryList } from '../composables/useFetchCountryList'

useHead({ title: 'Country statistics' })

const { data: countryPopularities, error } = useFetchCountryList()

/* svgMap library crashes on short hex code usage */
const normalizeColorFormat = color => {
  if (color.match(/^#([0-9a-fA-F]{3})$/)) {
    return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`
  }
  return color
}

const maxColor = normalizeColorFormat(window.getComputedStyle(document.documentElement).getPropertyValue('--bs-primary'))
const minColor = normalizeColorFormat(window.getComputedStyle(document.documentElement).getPropertyValue('--bs-border-color'))
const emptyColor = normalizeColorFormat(window.getComputedStyle(document.documentElement).getPropertyValue('--bs-secondary-bg'))

onMounted(() => {
  watch(countryPopularities, () => {
    if (countryPopularities.value.countryPopularities.length < 1) {
      return
    }

    const values = {}
    for (const country of countryPopularities.value.countryPopularities) {
      values[country.code] = { popularity: country.popularity }
    }

    // eslint-disable-next-line no-new
    new SvgMap({
      targetElementID: 'countries-map',
      colorNoData: emptyColor,
      colorMin: minColor,
      colorMax: maxColor,
      flagType: 'emoji',
      showZoomReset: true,
      data: {
        data: {
          popularity: {
            name: 'share',
            format: '{0}%',
            thousandSeparator: ','
          }
        },
        applyData: 'popularity',
        values
      }
    })
  }, { immediate: true })
})
</script>

<style lang="scss">
/* stylelint-disable */
$textColor: var(--bs-body-color);
$textColorLight: var(--bs-secondary-color);
$oceanColor: transparent;
$mapActiveStrokeColor: var(--bs-link-hover-color);
$mapControlsColor: var(--bs-body-color);
$mapControlsBackgroundColor: var(--bs-body-bg);
$mapTooltipColor: var(--bs-body-color);
$mapTooltipBackgroundColor: var(--bs-body-bg);

@import "~svgmap/src/scss/main";
</style>
