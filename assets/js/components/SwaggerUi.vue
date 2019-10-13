<template>
  <div id="swagger-ui"></div>
</template>

<style lang="scss">
  @import "~swagger-ui/dist/swagger-ui.css";

  .swagger-ui {
    .information-container {
      display: none;
    }
  }
</style>

<script>
import SwaggerUI from 'swagger-ui'

export default {
  name: 'swagger-ui',
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
