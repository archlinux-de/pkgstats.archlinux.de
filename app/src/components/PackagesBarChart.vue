<template>
  <div>
    <div class="alert alert-danger" role="alert" v-if="error != ''">{{ error }}</div>
    <loading-spinner absolute v-if="loading"></loading-spinner>
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
          <div class="progress bg-transparent progress--large"
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
      error: '',
      observer: null
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
      this.loading = true
      Promise.all(this.packages.map(pkg => this.apiPackagesService.fetchPackagePopularity(pkg)))
        .then(dataArray => {
          this.packagePopularities = this.sortPackagesByPopularity(dataArray)
        })
        .catch(error => {
          this.error = error
        })
        .finally(() => {
          this.loading = false
        })
    },
    observe  () {
      this.observer = new IntersectionObserver(entries => {
        if (entries[0].intersectionRatio <= 0) {
          return
        }
        this.fetchData()
        this.observer.disconnect()
      }, { rootMargin: '0px 0px 640px 0px' })
      this.observer.observe(this.$el)
    }
  },
  metaInfo () {
    if (this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  },
  mounted () {
    this.observe()
  },
  beforeDestroy () {
    if (this.observer) {
      this.observer.disconnect()
    }
  }
}
</script>
