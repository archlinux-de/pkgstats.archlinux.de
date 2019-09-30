import Vue from 'vue'
import PackageChart from './components/PackageChart'

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
