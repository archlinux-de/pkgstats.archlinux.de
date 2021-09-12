<template>
  <swagger-ui url="/api/doc.json"></swagger-ui>
</template>

<script setup>
import SwaggerUi from '../components/SwaggerUi'
import { useHead } from '@vueuse/head'

useHead({
  title: 'API documentation',
  meta: [{ name: 'robots', content: 'noindex' }]
})
</script>
