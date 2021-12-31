import { createRouter, createWebHistory } from 'vue-router'
import Packages from './views/Packages'
import Start from './views/Start'
import NotFound from './views/NotFound'

export default createRouter({
  history: createWebHistory(),
  linkActiveClass: 'active',
  routes: [
    { path: '/compare/packages', name: 'compare', component: () => import(/* webpackChunkName: "package-chart" */ './views/Compare') },
    { path: '/fun', name: 'fun', component: () => import(/* webpackChunkName: "fun" */ './views/Fun') },
    { path: '/impressum', name: 'impressum', component: () => import(/* webpackChunkName: "legal" */ './views/Impressum') },
    { path: '/packages/:package', name: 'package', component: () => import(/* webpackChunkName: "package-chart" */ './views/Package') },
    { path: '/packages', name: 'packages', component: Packages },
    { path: '/privacy-policy', name: 'privacy-policy', component: () => import(/* webpackChunkName: "legal" */ './views/PrivacyPolicy') },
    { path: '/', name: 'start', component: Start },
    { path: '/api/doc', name: 'api-doc', component: () => import(/* webpackChunkName: "api-doc" */ './views/ApiDoc') },
    { path: '/:pathMatch(.*)*', component: NotFound }
  ]
})
