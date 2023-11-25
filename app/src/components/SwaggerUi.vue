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
  const rootNode = root.value.attachShadow({ mode: 'open' })

  const styleNode = document.createElement('style')
  styleNode.innerHTML = Styles +
    `
    .information-container { display: none; }
    .swagger-ui .opblock .opblock-summary-path { max-width: calc(100% - 10rem); }
    @media (prefers-color-scheme: dark) {
      .swagger-ui { filter: invert(88%) hue-rotate(180deg); }
      .swagger-ui .microlight { filter: invert(100%) hue-rotate(180deg); }
      .swagger-ui input[type=text] { color: #3b4151; }
    }
    `
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
