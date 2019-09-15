import SwaggerUI from 'swagger-ui'

window.onload = () => {
  const swaggerUi = document.getElementById('swagger-ui')
  const swaggerUrl = swaggerUi.dataset.swaggerUrl
  SwaggerUI({
    domNode: swaggerUi,
    url: swaggerUrl,
    defaultModelsExpandDepth: 0
  })
}
