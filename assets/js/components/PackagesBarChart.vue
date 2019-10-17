<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error">{{ error }}</div>
    <loading-spinner v-if="loading"></loading-spinner>
    <table class="table table-sm">
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
      loading: true,
      packagePopularities: this.packages.map(pkg => ({ name: pkg, popularity: 0 })),
      error: ''
    }
  },
  components: {
    LoadingSpinner
  },
  methods: {
    sortPackagesByPopularity (pkgs) {
      return pkgs.sort((a, b) => Math.sign(b.popularity - a.popularity))
    },
    fetchData () {
      Promise.all(this.packages.map(pkg => this.apiPackagesService.fetchPackagePopularity(pkg)))
        .then(dataArray => { this.packagePopularities = this.sortPackagesByPopularity(dataArray) })
        .catch(error => { this.error = error })
        .finally(() => { this.loading = false })
    }
  },
  mounted () {
    this.fetchData()
  },
  metaInfo () {
    if (this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
