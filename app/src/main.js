import { createApp } from 'vue'
import { createHead } from '@vueuse/head'
import App from './App'
import router from './router'
import convertToDataSeries from './services/DataSeriesConverter'

const head = createHead()
const app = createApp(App)

app.use(router)
app.use(head)

app.provide('convertToDataSeries', convertToDataSeries)

app.mount('#app')
