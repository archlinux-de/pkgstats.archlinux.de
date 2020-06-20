<template>
  <div>
    <b-alert :show="error != ''" variant="danger">{{ error }}</b-alert>
    <loading-spinner absolute v-if="loading"></loading-spinner>
    <table class="table table-sm" v-b-visible="{ callback: visibilityChanged, once: true }">
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
          <b-progress
            :title="packagePopularity.popularity + '%'"
            class="bg-transparent"
            height="2em">
            <b-progress-bar
              :label="(packagePopularity.popularity > 5 ? packagePopularity.popularity + '%' : '')"
              :precision="2"
              :value="packagePopularity.popularity"
            />
          </b-progress>
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
      loading: false,
      packagePopularities: this.packages.map(pkg => ({ name: pkg, popularity: 0 })),
      error: ''
    }
  },
  components: {
    LoadingSpinner
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
