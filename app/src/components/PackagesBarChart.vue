<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error != ''">{{ error }}</div>
    <loading-spinner absolute v-if="loading"></loading-spinner>
    <table class="table table-sm table-borderless">
      <colgroup>
        <col class="w-25">
        <col class="w-75">
      </colgroup>
      <tr :key="packagePopularity.name" v-for="packagePopularity in packagePopularities">
        <td>
          <router-link :to="{name: 'package', params:{package: packagePopularity.name}}">{{ packagePopularity.name }}
          </router-link>
        </td>
        <td>
          <div class="progress bg-transparent progress-large"
               :title="packagePopularity.popularity + '%'">
            <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                 :style="`width: ${packagePopularity.popularity}%`"
                 :aria-valuenow="packagePopularity.popularity"
                 v-text="(packagePopularity.popularity > 5 ? packagePopularity.popularity + '%' : '')"></div>
          </div>
        </td>
      </tr>
    </table>
  </div>
</template>

<script setup>
import { defineProps, toRefs, ref, inject, onMounted } from 'vue'
import LoadingSpinner from './LoadingSpinner'

const apiPackagesService = inject('apiPackagesService')

const props = defineProps({
  packages: {
    type: Array,
    required: true
  }
})
const { packages } = toRefs(props)
const packagePopularities = ref(packages.value.map(packageName => ({ name: packageName, popularity: 0 })))
const error = ref('')
const loading = ref(false)

const sortPackagesByPopularity = packagePopularities => packagePopularities.sort((a, b) => Math.sign(b.popularity - a.popularity))

const fetchData = () => {
  loading.value = true
  Promise.all(packages.value.map(packageName => apiPackagesService.fetchPackagePopularity(packageName)))
    .then(dataArray => {
      packagePopularities.value = sortPackagesByPopularity(dataArray)
    })
    .catch(e => {
      error.value = e.toString()
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(() => {
  fetchData()
})
</script>
