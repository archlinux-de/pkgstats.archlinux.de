import Vue from 'vue'
import App from './App'
import router from './router'
import VueMeta from 'vue-meta'
import createApiPackagesService from './services/ApiPackagesService'
import convertToDataSeries from './services/DataSeriesConverter'

Vue.use(VueMeta)

Vue.config.productionTip = false
new Vue({
  router,
  render: h => h(App),
  provide: {
    apiPackagesService: createApiPackagesService(fetch),
    convertToDataSeries: convertToDataSeries
  }
}).$mount('#app')
