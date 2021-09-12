<template>
  <main class="container" role="main">
    <div class="alert alert-danger" role="alert">
      <h4 class="alert-heading">File not found</h4>
      <p>The requested file <strong>{{ currentUrl() }}</strong> was not found.</p>
      <hr>
      <p class="mb-0">Contact us if the problem persists. We will resolve it as soon as possible.</p>
    </div>
  </main>
</template>

<script setup>
import { useHead } from '@vueuse/head'

const currentUrl = () => window.location.pathname

useHead({
  title: 'File not found',
  meta: [{ name: 'robots', content: 'noindex' }]
})
</script>
