<template>
  <div>
    <b-form-group>
      <b-form-input
        autofocus
        debounce="250"
        placeholder="Package name"
        type="search"
        v-model="query"></b-form-input>
    </b-form-group>
    <loading-spinner absolute v-if="loading && offset === 0"></loading-spinner>
    <table class="table table-striped table-bordered table-sm" v-show="data.packagePopularities.length > 0">
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
          <b-progress
            :max="100"
            :precision="2"
            :value="pkg.popularity"
            class="bg-transparent"
            height="2em"
            show-progress></b-progress>
        </td>
      </tr>
      </tbody>
    </table>
    <b-alert :show="error != ''" variant="danger">{{ error }}</b-alert>
    <b-alert :show="data.total === data.count" variant="info">{{ data.total }} packages found</b-alert>
    <loading-spinner v-if="loading && offset > 0"></loading-spinner>
    <div v-observe-visibility="visibilityChanged"></div>
  </div>
</template>

<script>
import LoadingSpinner from './LoadingSpinner'
import { ObserveVisibility } from 'vue-observe-visibility'

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
  components: {
    LoadingSpinner
  },
  directives: {
    'observe-visibility': ObserveVisibility
  },
  data () {
    return {
      loading: true,
      data: {
        count: this.limit,
        total: this.limit,
        limit: this.limit,
        offset: 0,
        packagePopularities: this.createInitialPackagePopularities()
      },
      query: this.initialQuery,
      error: '',
      offset: 0
    }
  },
  watch: {
    query () {
      if (this.query.length > 255) {
        this.query = this.query.substring(0, 255)
      }
      this.query = this.query.replace(/(^[^a-zA-Z0-9]|[^a-zA-Z0-9@:.+_-]+)/, '')
      this.offset = 0
      this.fetchData()
    }
  },
  methods: {
    fetchData () {
      this.loading = true
      const query = this.query
      const offset = this.offset
      return this.apiPackagesService
        .fetchPackageList({
          query: this.query,
          limit: this.limit,
          offset: this.offset
        })
        .then(data => {
          if (query === this.query && offset === this.offset) {
            if (offset === 0) {
              this.data = data
            } else {
              this.data.count += data.count
              this.data.packagePopularities.push(...data.packagePopularities)
            }
          }
        })
        .catch(error => { this.error = error })
        .finally(() => { this.loading = false })
    },
    createInitialPackagePopularities () {
      return Array.from({ length: this.limit }, () => ({
        name: String.fromCharCode(8239),
        popularity: 0
      }))
    },
    visibilityChanged (isVisible) {
      if (!this.loading && isVisible) {
        if (this.data.count < this.data.total) {
          this.offset += this.limit
          this.fetchData()
        }
      }
    }
  },
  mounted () {
    this.fetchData()
  },
  metaInfo () {
    if (this.data.count < 1 || this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
