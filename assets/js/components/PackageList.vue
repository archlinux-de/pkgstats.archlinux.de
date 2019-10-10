<template>
  <div>
    <div class="package-list-header">
      The top {{ data.count }} of {{ data.total }} total packages
      <div class="form-group">
        <input class="form-control"
               max="255" min="0" pattern="^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]*$"
               placeholder="Package name" type="text"
               v-model="query"/>
      </div>
      <div class="spinner-container" v-if="loading">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
    </div>
    <table class="table table-striped table-bordered table-sm">
      <thead>
      <tr>
        <th scope="col">Package</th>
        <th scope="col">Popularity</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="pkg in data.packagePopularities">
        <td class="text-nowrap"><a :href="packageUrlTemplate.replace('{package}', encodeURI(pkg.name))">{{ pkg.name
          }}</a></td>
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
  </div>
</template>

<script>
  import ApiPackagesService from '../services/ApiPackagesService'

  export default {
    name: 'PackageList',
    props: {
      initialQuery: {
        type: String,
        required: false
      },
      startMonth: {
        type: Number,
        required: false
      },
      endMonth: {
        type: Number,
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
        data: {},
        packageUrlTemplate: '/packages/{package}',
        missedQuery: false,
        query: this.initialQuery
      }
    },
    watch: {
      query: function () {
        if (this.query.length > 255) {
          this.query = this.query.substring(0, 255)
        }
        this.query = this.query.replace(/(^[^a-zA-Z0-9]|[^a-zA-Z0-9@:\.+_-]+)/, '')
        if (!this.loading) {
          this.fetchData()
        } else {
          this.missedQuery = true
        }
      },
      loading: function () {
        if (!this.loading && this.missedQuery) {
          this.missedQuery = false
          this.fetchData()
        }
      }
    },
    methods: {
      fetchData: function () {
        this.loading = true
        ApiPackagesService
          .fetchPackageList({
            query: this.query,
            startMonth: this.startMonth,
            endMonth: this.endMonth,
            limit: this.limit
          })
          .then(data => { this.data = data })
          .catch(error => console.error(error))
          .finally(() => {this.loading = false})
      }
    },
    mounted () {
      this.fetchData()
    }
  }
</script>
