<template>
  <main class="container" role="main">
    <div class="alert alert-danger" role="alert">
      <h4 class="alert-heading">File not found</h4>
      <p>The requested file <strong>{{ currentUrl }}</strong> was not found.</p>
      <hr>
      <p class="mb-0">Contact us if the problem persists. We will resolve it as soon as possible.</p>
    </div>
  </main>
</template>

<script>
export default {
  name: 'NotFound',
  data () {
    return { currentUrl: window.location.pathname }
  },
  metaInfo () {
    return {
      title: 'File not found',
      meta: [{ vmid: 'robots', name: 'robots', content: 'noindex,nofollow' }]
    }
  }
}
</script>
