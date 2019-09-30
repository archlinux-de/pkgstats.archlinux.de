<template>
  <div>
    <div class="package-list-header">
      The top {{ data.count }} of {{ data.total }} total packages
      <form class="form-group">
        <input class="form-control"
               max="255" min="0" pattern="^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]*$"
               placeholder="Package name" type="text"
               v-model="query"/>
      </form>
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
        <td class="text-nowrap"><a :href="packageUrlTemplate.replace('_package_', encodeURI(pkg.name))">{{ pkg.name
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
  // support IE 11
  import 'whatwg-fetch'

  export default {
    name: 'PackageList',
    data () {
      return {
        loading: true,
        data: {},
        packagesUrl: this.$parent.$data.packagesUrl,
        packageUrlTemplate: this.$parent.$data.packageUrlTemplate,
        query: '',
        missedQuery: false
      }
    },
    watch: {
      packagesUrl: function () {
        this.fetchData()
      },
      query: function () {
        if (this.query.length > 255) {
          this.query = this.query.substring(0, 255)
        }
        this.query = this.query.replace(/(^[^a-zA-Z0-9]|[^a-zA-Z0-9@:\.+_-]+)/, '')
        if (!this.loading) {
          this.updateUrl()
        } else {
          this.missedQuery = true
        }
      },
      loading: function () {
        if (!this.loading && this.missedQuery) {
          this.missedQuery = false
          this.updateUrl()
        }
      }
    },
    methods: {
      fetchData: function () {
        this.loading = true
        fetch(this.packagesUrl, {
          credentials: 'omit',
          headers: new Headers({ Accept: 'application/json' })
        })
          .then(response => response.json())
          .then(data => {
            this.data = data
            this.loading = false
          })
          .catch(e => {
            this.loading = false
            const error = document.createElement('div')
            error.classList.add('alert')
            error.classList.add('alert-danger')
            error.setAttribute('role', 'alert')
            error.innerText = e.toString()
            this.$el.appendChild(error)
          })
      },
      updateUrl: function () {
        if (this.packagesUrl.match(/&query=/)) {
          this.packagesUrl = this.packagesUrl.replace(/&query=.*/, '')
        }
        this.packagesUrl += `&query=${this.query}`
      }
    },
    mounted () {
      this.fetchData()
    }
  }
</script>
