<template>
  <swagger-ui url="/api/doc.json"></swagger-ui>
</template>

<script>
  import SwaggerUi from '@/js/components/SwaggerUi'

  export default {
    name: 'ApiDoc',
    components: {
      SwaggerUi
    },
    metaInfo: {
      title: 'API documentation',
      meta: [{ vmid: 'robots', name: 'robots', content: 'noindex' }]
    }
  }
</script>
