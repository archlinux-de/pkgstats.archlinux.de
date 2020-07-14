<template>
  <div></div>
</template>

<script>
import SwaggerUI from 'swagger-ui'
import retargetEvents from 'react-shadow-dom-retarget-events'
import Styles from '!css-loader!swagger-ui/dist/swagger-ui.css' // eslint-disable-line

export default {
  name: 'swagger-ui',
  props: {
    url: {
      type: String,
      required: true
    }
  },
  mounted () {
    let rootNode = this.$el

    if (HTMLElement.prototype.attachShadow) {
      rootNode = rootNode.attachShadow({ mode: 'open' })
      retargetEvents(rootNode)
    }

    const styleNode = document.createElement('style')
    styleNode.innerHTML = Styles + '.information-container { display: none;}'
    rootNode.appendChild(styleNode)

    const swaggerNode = document.createElement('div')
    rootNode.appendChild(swaggerNode)

    SwaggerUI({
      domNode: swaggerNode,
      url: this.url,
      defaultModelsExpandDepth: 0
    })
  }
}
</script>
