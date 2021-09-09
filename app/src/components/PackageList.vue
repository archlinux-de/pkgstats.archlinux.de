<template>
  <div>
    <div class="input-group mb-4">
      <span class="input-group-text" id="package-search-label">Package name</span>
      <input class="form-control" placeholder="e.g. pacman" type="search" aria-describedby="package-search-label" v-model="query">
    </div>
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
          <div class="progress bg-transparent progress--large"
               :title="pkg.popularity + '%'">
            <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                 :style="`width: ${pkg.popularity}%`"
                 :aria-valuenow="pkg.popularity"
            v-text="(pkg.popularity > 5 ? pkg.popularity + '%' : '')"></div>
          </div>
        </td>
      </tr>
      </tbody>
    </table>
    <div role="alert" class="alert alert-danger" v-if="error != ''">{{ error }}</div>
    <div role="alert" v-if="data.total === data.count" class="alert alert-info">{{ data.total }} packages found</div>
    <loading-spinner v-if="loading && offset > 0"></loading-spinner>
    <div id="items-end"></div>
  </div>
</template>

<script>
import LoadingSpinner from './LoadingSpinner'

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
      offset: 0,
      observer: null
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
        .catch(error => {
          this.error = error
        })
        .finally(() => {
          this.loading = false
        })
    },
    createInitialPackagePopularities () {
      return Array.from({ length: this.limit }, () => ({
        name: String.fromCharCode(8239),
        popularity: 0
      }))
    },
    visibilityChanged () {
      if (!this.loading) {
        if (this.data.count < this.data.total) {
          this.offset += this.limit
          this.fetchData()
        }
      }
    },
    observeItemsEnd () {
      const observer = new IntersectionObserver(entries => {
        if (entries[0].intersectionRatio <= 0) {
          return
        }
        this.visibilityChanged()
      }, { rootMargin: '0px 0px 0px 0px' })
      observer.observe(document.getElementById('items-end'))
    }
  },
  mounted () {
    this.fetchData()
    this.observeItemsEnd()
  },
  beforeDestroy () {
    if (this.observer) {
      this.observer.disconnect()
    }
  },
  metaInfo () {
    if (this.data.count < 1 || this.error) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
