/* eslint-env browser */
import SwaggerUI from 'swagger-ui'
import Styles from '!css-loader!postcss-loader!sass-loader!../css/swagger_ui.scss' // eslint-disable-line

let domNode = document.getElementById('swagger-ui')
const url = domNode.dataset.url

if (HTMLElement.prototype.attachShadow) {
  domNode = domNode.attachShadow({ mode: 'open' })
}

SwaggerUI({
  domNode: domNode,
  url: url,
  defaultModelsExpandDepth: 0
})

const style = document.createElement('style')
style.innerHTML = Styles
domNode.appendChild(style)
