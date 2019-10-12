import Vue from 'vue'
import VueMeta from 'vue-meta'
import App from './App.vue'
import router from './router'
// support IE 11
import 'whatwg-fetch'

Vue.config.productionTip = false
Vue.use(VueMeta)

new Vue({
  router,
  render: h => h(App)
}).$mount('#app')
