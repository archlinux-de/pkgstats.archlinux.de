import Vue from 'vue'
import VueMeta from 'vue-meta'
import VueObserveVisibility from 'vue-observe-visibility'
import App from './App'
import router from './router'
import createApiPackagesService from './services/ApiPackagesService'
import convertToDataSeries from './services/DataSeriesConverter'

Vue.config.productionTip = false
Vue.use(VueMeta)
Vue.use(VueObserveVisibility)

new Vue({
  router,
  render: h => h(App),
  provide: {
    apiPackagesService: createApiPackagesService(fetch),
    convertToDataSeries: convertToDataSeries
  }
}).$mount('#app')
