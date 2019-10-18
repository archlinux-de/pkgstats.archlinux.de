import Vue from 'vue'
import VueMeta from 'vue-meta'
import App from './App'
import router from './router'
import createApiPackagesService from './services/ApiPackagesService'
import convertToDataSeries from './services/DataSeriesConverter'

Vue.config.productionTip = false
Vue.use(VueMeta)

new Vue({
  router,
  render: h => h(App),
  provide: {
    apiPackagesService: createApiPackagesService(fetch),
    convertToDataSeries: convertToDataSeries
  }
}).$mount('#app')
