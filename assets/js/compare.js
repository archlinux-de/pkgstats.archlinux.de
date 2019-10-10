/* eslint-env browser */
import Vue from 'vue'
import PackageChart from './components/PackageChart'

let packages = location.hash
  .replace(/^#packages=/, '')
  .split(',')
  .filter(pkg => {
    return pkg.length > 0
  })

Array.from(new Set(packages)).sort()
// limit the number of line graphs
packages = packages.slice(0, 10)
location.hash = 'packages=' + packages.join(',')

new Vue({
  components: {
    PackageChart
  },
  data: {
    packages
  }
}).$mount('#app')
