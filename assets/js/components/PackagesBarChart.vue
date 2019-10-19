<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error">{{ error }}</div>
    <loading-spinner v-if="loading" absolute></loading-spinner>
    <table class="table table-sm" v-observe-visibility="{ callback: visibilityChanged, once: true }">
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
          <div class="progress">
            <div :aria-valuenow="packagePopularity.popularity" :style="`width: ${packagePopularity.popularity}%`"
                 aria-valuemax="100"
                 aria-valuemin="0" class="progress-bar bg-primary" role="progressbar">
              {{ packagePopularity.popularity > 5 ? packagePopularity.popularity + '%' : ''}}
            </div>
          </div>
        </td>
      </tr>
    </table>
  </div>
</template>

<script>
import LoadingSpinner from './LoadingSpinner'
import { ObserveVisibility } from 'vue-observe-visibility'

export default {
  name: 'PackagesBarChart',
  inject: ['apiPackagesService'],
  props: {
    packages: {
      type: Array,
      required: true
    }
  },
  data () {
    return {
      loading: false,
      packagePopularities: this.packages.map(pkg => ({ name: pkg, popularity: 0 })),
      error: ''
    }
  },
  components: {
    LoadingSpinner
  },
  directives: {
    'observe-visibility': ObserveVisibility
  },
  methods: {
    visibilityChanged (isVisible) {
      if (isVisible) {
        this.fetchData()
      }
    },
    sortPackagesByPopularity (pkgs) {
      return pkgs.sort((a, b) => Math.sign(b.popularity - a.popularity))
    },
    fetchData () {
      this.loading = true
      Promise.all(this.packages.map(pkg => this.apiPackagesService.fetchPackagePopularity(pkg)))
        .then(dataArray => { this.packagePopularities = this.sortPackagesByPopularity(dataArray) })
        .catch(error => { this.error = error })
        .finally(() => { this.loading = false })
    }
  },
  metaInfo () {
    if (this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
