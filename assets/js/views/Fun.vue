<template>
  <main class="container-fluid" role="main">
    <h1 class="mb-4">Fun statistics</h1>
    <div id="app">
      <fun-statistics></fun-statistics>
    </div>
  </main>
</template>

<script>
  import FunStatistics from '@/js/components/FunStatistics'

  export default {
    name: 'Fun',
    components: {
      FunStatistics
    },
    metaInfo: {
      title: 'Fun statistics'
    }
  }
</script>
