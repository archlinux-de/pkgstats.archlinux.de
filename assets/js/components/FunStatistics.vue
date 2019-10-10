<template>
  <div>
    <table class="table table-sm">
      <colgroup>
        <col class="w-25">
        <col class="w-75">
      </colgroup>
      <template v-for="(pkgs, title) in data">
        <tr>
          <th class="text-center" colspan="2"><a :href="createComparePackagesLink(pkgs)">{{ title }}</a></th>
        </tr>
        <tr v-for="(pkgdata, pkg) in pkgs">
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
  import FunConfig from '../config/fun'
  import ApiPackagesService from '../services/ApiPackagesService'

  export default {
    name: 'FunStatistics',
    props: {
      startMonth: {
        type: Number,
        required: false
      },
      endMonth: {
        type: Number,
        required: false
      }
    },
    data () {
      return {
        data: {},
        comparePackagesUrl: '/compare/packages'
      }
    },
    methods: {
      fetchPackagePopularity: function (pkg) {
        return ApiPackagesService.fetchPackagePopularity(pkg, { startMonth: this.startMonth, endMonth: this.endMonth })
          .catch(error => console.error(error))
      },
      fetchData: function () {
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
      createComparePackagesLink: function (pkgs) {
        const ps = []
        Object.entries(pkgs).forEach(p => {
          p[1].packages.forEach(v => ps.push(v))
        })
        return this.comparePackagesUrl + '#packages=' + ps.sort().join(',')
      }
    },
    mounted () {
      this.fetchData()
    }
  }
</script>
