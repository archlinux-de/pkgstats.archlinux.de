<template>
  <div id="swagger-ui"></div>
</template>

<script>
  import SwaggerUI from 'swagger-ui'

  export default {
    name: 'ApiDoc',
    props: {
      url: {
        type: String,
        required: true
      }
    },
    mounted () {
      SwaggerUI({
        domNode: this.$el,
        url: this.url,
        defaultModelsExpandDepth: 0
      })
    }
  }
</script>
