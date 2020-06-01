import Vue from 'vue'
import VueMeta from 'vue-meta'

import {
  AlertPlugin,
  FormGroupPlugin,
  FormInputPlugin,
  JumbotronPlugin,
  LayoutPlugin,
  NavbarPlugin,
  ProgressPlugin,
  SpinnerPlugin
} from 'bootstrap-vue'

import App from './App'
import router from './router'
import createApiPackagesService from './services/ApiPackagesService'
import convertToDataSeries from './services/DataSeriesConverter'

Vue.config.productionTip = false
Vue.use(VueMeta)

Vue.use(LayoutPlugin)
Vue.use(NavbarPlugin)
Vue.use(JumbotronPlugin)
Vue.use(FormInputPlugin)
Vue.use(FormGroupPlugin)
Vue.use(AlertPlugin)
Vue.use(SpinnerPlugin)
Vue.use(ProgressPlugin)

new Vue({
  router,
  render: h => h(App),
  provide: {
    apiPackagesService: createApiPackagesService(fetch),
    convertToDataSeries: convertToDataSeries
  }
}).$mount('#app')
