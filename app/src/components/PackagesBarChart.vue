<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error.length > 0">{{ error }}</div>
    <loading-spinner absolute v-if="isFetching"></loading-spinner>
    <table class="table table-sm table-borderless" v-if="error.length === 0" data-test="packages-bar-chart">
      <colgroup>
        <col class="w-25">
        <col class="w-75">
      </colgroup>
      <tr :key="packagePopularity.name" v-for="packagePopularity in packagePopularities" :data-test-name="packagePopularity.name">
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
import LoadingSpinner from './LoadingSpinner'
import { useFetchPackagesPopularity } from '../composables/useFetchPackagesPopularity'

const props = defineProps({
  packages: {
    type: Array,
    required: true
  }
})

// eslint-disable-next-line vue/no-setup-props-destructure
const { data: packagePopularities, isFetching, error } = useFetchPackagesPopularity(props.packages)
</script>
