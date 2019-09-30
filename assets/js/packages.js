import Vue from 'vue'
import PackageList from './components/PackageList'

const AppElement = document.querySelector('#app')
const packagesUrl = AppElement.dataset.packagesUrl
const packageUrlTemplate = AppElement.dataset.packageUrlTemplate

new Vue({
  components: {
    PackageList
  },
  data: {
    packagesUrl: packagesUrl,
    packageUrlTemplate: packageUrlTemplate
  }
}).$mount(AppElement)
