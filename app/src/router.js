import { createRouter, createWebHistory } from 'vue-router'
import Fun from './views/Fun'
import Impressum from './views/Impressum'
import Packages from './views/Packages'
import PrivacyPolicy from './views/PrivacyPolicy'
import Start from './views/Start'
import NotFound from './views/NotFound'

export default createRouter({
  history: createWebHistory(),
  linkActiveClass: 'active',
  routes: [
    { path: '/compare/packages', name: 'compare', component: () => import(/* webpackChunkName: "package-chart" */ './views/Compare') },
    { path: '/fun', name: 'fun', component: Fun },
    { path: '/impressum', name: 'impressum', component: Impressum },
    { path: '/packages/:package', name: 'package', component: () => import(/* webpackChunkName: "package-chart" */ './views/Package') },
    { path: '/packages', name: 'packages', component: Packages },
    { path: '/privacy-policy', name: 'privacy-policy', component: PrivacyPolicy },
    { path: '/', name: 'start', component: Start },
    { path: '/api/doc', name: 'api-doc', component: () => import(/* webpackChunkName: "api-doc" */ './views/ApiDoc') },
    { path: '/:pathMatch(.*)*', component: NotFound }
  ]
})
