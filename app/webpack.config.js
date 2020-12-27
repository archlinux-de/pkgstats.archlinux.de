const { merge } = require('webpack-merge')
const common = require('./webpack.common.js')

module.exports = (env, argv) => {
  const configMode = typeof argv.mode !== 'undefined' && argv.mode === 'development' ? 'dev' : 'prod'

  return merge(common, require(`./webpack.${configMode}.js`))
}
