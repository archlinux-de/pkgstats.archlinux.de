<template>
  <div>
    <div class="alert alert-danger text-left" role="alert" v-if="errors.length > 0">
      <ul :key="id" class="list-group list-unstyled" v-for="(error, id) in errors">
        <li>{{ error }}</li>
      </ul>
    </div>
    <table class="table table-sm">
      <colgroup>
        <col class="w-25">
        <col class="w-75">
      </colgroup>
      <template v-for="(pkgs, title) in data">
        <tr :key="title">
          <th class="text-center" colspan="2">
            <router-link :to="{name: 'compare', hash: createComparePackagesHash(pkgs)}">{{ title }}</router-link>
          </th>
        </tr>
        <tr :key="pkgdata.name" v-for="(pkgdata, pkg) in pkgs">
          <td>{{ pkg }}</td>
          <td>
            <div class="progress">
              <div :aria-valuenow="pkgdata.popularity" :style="`width: ${pkgdata.popularity}%`"
                   aria-valuemax="100"
                   aria-valuemin="0" class="progress-bar bg-primary" role="progressbar">
                {{ pkgdata.popularity > 5 ? pkgdata.popularity + '%' : ''}}
              </div>
            </div>
          </td>
        </tr>
      </template>
    </table>
  </div>
</template>

<script>
import FunConfig from '@/js/config/fun'

export default {
  name: 'FunStatistics',
  inject: ['apiPackagesService'],
  data () {
    return {
      data: {},
      errors: []
    }
  },
  methods: {
    fetchPackagePopularity (pkg) {
      return this.apiPackagesService.fetchPackagePopularity(pkg)
        .catch(error => {
          this.errors.push(error)
          return 0
        })
    },
    fetchData () {
      Object.entries(FunConfig).forEach(([statTitle, statPkgs]) => {
        this.$set(this.data, statTitle, {})
        Object.entries(statPkgs).forEach(([pkgTitle, pkgnames]) => {
          if (!Array.isArray(pkgnames)) {
            pkgnames = [pkgnames]
          }
          this.$set(this.data[statTitle], pkgTitle, {
            popularity: 0,
            packages: pkgnames
          })
          Promise.all(
            pkgnames.map(pkgname => this.fetchPackagePopularity(pkgname))
          ).then(dataArray => {
            this.data[statTitle][pkgTitle] = {
              popularity: Math.round(dataArray.reduce((total, value) => total + value)),
              packages: pkgnames
            }
            const rankedPackages = {}
            Object.keys(this.data[statTitle]).sort((a, b) => {
              return Math.sign(this.data[statTitle][b].popularity - this.data[statTitle][a].popularity)
            }).forEach(key => {
              rankedPackages[key] = this.data[statTitle][key]
            })
            this.$set(this.data, statTitle, rankedPackages)
          })
        })
      })
    },
    createComparePackagesHash (pkgs) {
      const ps = []
      Object.entries(pkgs).forEach(p => {
        p[1].packages.forEach(v => ps.push(v))
      })
      return '#packages=' + ps.sort().join(',')
    }
  },
  mounted () {
    this.fetchData()
  },
  metaInfo () {
    if (this.errors.length > 0) {
      return { meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }] }
    }
  }
}
</script>
