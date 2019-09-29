<template>
  <div>
    <div class="package-list-header">
      The top {{ data.count }} of {{ data.total }} total packages
      <form class="form-group">
        <input class="form-control" pattern="^[^-/]{1}[^/\s]{1,255}$" type="text" v-model="query"/>
      </form>
    </div>
    <div class="spinner-container" v-if="loading">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
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
        query: ''
      }
    },
    watch: {
      packagesUrl: function () {
        this.fetchData()
      },
      query: function () {
        if (this.packagesUrl.match(/query=/)) {
          this.packagesUrl = this.packagesUrl.replace(/query=.*/, '')
        }
        this.packagesUrl += `&query=${this.query}`
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
      }
    },
    mounted () {
      this.fetchData()
    }
  }
</script>
