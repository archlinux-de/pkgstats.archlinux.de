<template>
  <div ref="root"></div>
</template>

<script setup>
import SwaggerUI from 'swagger-ui'
import Styles from '!css-loader!swagger-ui/dist/swagger-ui.css' // eslint-disable-line
import { onMounted, toRefs, ref } from 'vue'

const props = defineProps({
  url: {
    type: String,
    required: true
  }
})
const { url } = toRefs(props)
const root = ref(null)

onMounted(() => {
  let rootNode = root.value

  if (HTMLElement.prototype.attachShadow) {
    rootNode = rootNode.attachShadow({ mode: 'open' })
  }

  const styleNode = document.createElement('style')
  styleNode.innerHTML = Styles +
    '.information-container { display: none; }' +
    '.swagger-ui .opblock .opblock-summary-path { max-width: calc(100% - 10rem); }'
  rootNode.appendChild(styleNode)

  const swaggerNode = document.createElement('div')
  rootNode.appendChild(swaggerNode)

  SwaggerUI({
    domNode: swaggerNode,
    url: url.value,
    defaultModelsExpandDepth: 0
  })
})

</script>
