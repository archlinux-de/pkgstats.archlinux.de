import Vue from 'vue'
import App from './App'
import router from './router'
import VueMeta from 'vue-meta'
import {
  AlertPlugin,
  FormGroupPlugin,
  FormInputPlugin,
  JumbotronPlugin,
  LayoutPlugin,
  ProgressPlugin,
  SpinnerPlugin,
  VBVisiblePlugin
} from 'bootstrap-vue'
import createApiPackagesService from './services/ApiPackagesService'
import convertToDataSeries from './services/DataSeriesConverter'

Vue.use(VueMeta)
Vue.use(LayoutPlugin)
Vue.use(JumbotronPlugin)
Vue.use(FormInputPlugin)
Vue.use(FormGroupPlugin)
Vue.use(AlertPlugin)
Vue.use(SpinnerPlugin)
Vue.use(ProgressPlugin)
Vue.use(VBVisiblePlugin)

Vue.config.productionTip = false
new Vue({
  router,
  render: h => h(App),
  provide: {
    apiPackagesService: createApiPackagesService(fetch),
    convertToDataSeries: convertToDataSeries
  }
}).$mount('#app')
