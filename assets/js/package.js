import Vue from 'vue'
import PackageChart from './components/PackageChart'

Vue.config.productionTip = false

const AppElement = document.querySelector('#app')
const url = AppElement.dataset.url

new Vue({
  components: {
    PackageChart
  },
  data: {
    urls: [url]
  }
}).$mount(AppElement)
