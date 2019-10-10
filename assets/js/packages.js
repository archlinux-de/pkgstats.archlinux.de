/* eslint-env browser */
import Vue from 'vue'
import PackageList from './components/PackageList'

const vue = new Vue({
  components: {
    PackageList
  },
  data: {
    initialQuery: location.hash.replace(/^#query=/, '')
  }
}).$mount('#app')

// @TODO: Find a better way to bind our event to the PackageList component
vue.$children.forEach(child => {
  if (child.$vnode.componentOptions.tag === 'package-list') {
    child.$watch('query', query => { location.hash = 'query=' + query })
  }
})
