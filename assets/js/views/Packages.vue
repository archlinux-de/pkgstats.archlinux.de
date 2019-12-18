<template>
  <b-container fluid role="main" tag="main">
    <h1 class="mb-4">Package statistics</h1>
    <package-list :initial-query="initialQuery" :limit="40"></package-list>
  </b-container>
</template>

<script>
import PackageList from '@/js/components/PackageList'

export default {
  name: 'Packages',
  components: {
    PackageList
  },
  data () {
    return { initialQuery: this.$route.hash.replace(/^#query=/, '') }
  },
  metaInfo () {
    return {
      title: 'Package statistics',
      link: [{ rel: 'canonical', href: this.$route.path }]
    }
  },
  mounted () {
    // @TODO: Find a better way to bind our event to the PackageList component
    this.$children.forEach(child => {
      if (child.$vnode.componentOptions.tag === 'package-list') {
        child.$watch('query', query => { this.$router.replace({ name: 'packages', hash: '#query=' + query }) })
      }
    })
  }
}
</script>
