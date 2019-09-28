import Vue from 'vue'
import Chart from './components/Chart'

Vue.config.productionTip = false

const AppElement = document.querySelector('#app')
const url = AppElement.dataset.url

new Vue({
  components: {
    'pkgstats-chart': Chart
  },
  data: {
    urls: [url]
  }
}).$mount(AppElement)
