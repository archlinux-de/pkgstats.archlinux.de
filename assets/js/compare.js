/* eslint-env browser */
import Vue from 'vue'
import PackageChart from './components/PackageChart'

Vue.config.productionTip = false

const AppElement = document.querySelector('#app')
const urlTemplate = AppElement.dataset.urlTemplate

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

const urls = packages.map(packageName => urlTemplate.replace('_package_', packageName))

new Vue({
  components: {
    PackageChart
  },
  data: {
    urls: urls
  }
}).$mount(AppElement)
