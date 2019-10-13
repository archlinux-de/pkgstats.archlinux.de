<template>
  <div>
    <div class="package-list-header">
      The top {{ data.count }} of {{ data.total }} total packages
      <div class="form-group">
        <input class="form-control"
               max="255" min="0" pattern="^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]*$"
               placeholder="Package name" type="text"
               v-model="query"/>
      </div>
      <div class="spinner-container" v-if="loading">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
    </div>
    <table class="table table-striped table-bordered table-sm" v-if="data.packagePopularities.length > 0">
      <thead>
      <tr>
        <th scope="col">Package</th>
        <th scope="col">Popularity</th>
      </tr>
      </thead>
      <tbody>
      <tr :key="id" v-for="(pkg, id) in data.packagePopularities">
        <td class="text-nowrap">
          <router-link :to="{name: 'package', params: {package: pkg.name}}">{{ pkg.name }}</router-link>
        </td>
        <td class="w-75">
          <div :title="pkg.popularity+'%'" class="progress bg-transparent">
            <div :aria-valuenow="pkg.popularity" :style="'width:'+pkg.popularity+'%'"
                 aria-valuemax="100" aria-valuemin="0" class="progress-bar bg-primary"
                 role="progressbar">
              {{ pkg.popularity > 5 ? pkg.popularity + '%' : ''}}
            </div>
          </div>
        </td>
      </tr>
      </tbody>
    </table>
    <div class="alert alert-warning" role="alert" v-else-if="!error && !loading">No packages found</div>
    <div class="alert alert-danger" role="alert" v-if="error">{{ error }}</div>
  </div>
</template>

<style lang="scss">
  .package-list-header {
    .spinner-container {
      position: absolute;
      left: 50%;
      transform: translate(-50%, 150%);
    }
  }
</style>

<script>
export default {
  name: 'PackageList',
  inject: ['apiPackagesService'],
  props: {
    initialQuery: {
      type: String,
      required: false
    },
    limit: {
      type: Number,
      required: false
    }
  },
  data () {
    return {
      loading: true,
      data: { packagePopularities: [] },
      missedQuery: false,
      query: this.initialQuery,
      error: ''
    }
  },
  watch: {
    query () {
      if (this.query.length > 255) {
        this.query = this.query.substring(0, 255)
      }
      this.query = this.query.replace(/(^[^a-zA-Z0-9]|[^a-zA-Z0-9@:.+_-]+)/, '')
      if (!this.loading) {
        this.fetchData()
      } else {
        this.missedQuery = true
      }
    },
    loading () {
      if (!this.loading && this.missedQuery) {
        this.missedQuery = false
        this.fetchData()
      }
    }
  },
  methods: {
    fetchData () {
      this.loading = true
      this.apiPackagesService
        .fetchPackageList({
          query: this.query,
          limit: this.limit
        })
        .then(data => { this.data = data })
        .catch(error => { this.error = error })
        .finally(() => { this.loading = false })
    }
  },
  mounted () {
    this.fetchData()
  },
  metaInfo () {
    if (this.data.packagePopularities.length < 1 || this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
