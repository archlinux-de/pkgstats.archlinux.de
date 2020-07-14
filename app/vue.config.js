const CompressionPlugin = require('compression-webpack-plugin')

module.exports = {
  lintOnSave: false,
  productionSourceMap: false,
  devServer: {
    disableHostCheck: true
  },
  configureWebpack: config => {
    if (!process.env.VUE_CLI_MODERN_BUILD) {
      config.entry.app.unshift('whatwg-fetch')
      config.entry.app.unshift('intersection-observer')
    }

    if (process.env.NODE_ENV === 'production') {
      config.plugins.push(new CompressionPlugin({ filename: '[path].gz[query]', algorithm: 'gzip' }))
      config.plugins.push(new CompressionPlugin({ filename: '[path].br[query]', algorithm: 'brotliCompress' }))
    }
  },
  chainWebpack: config => {
    config.resolve.alias.set('bootstrap-vue$', 'bootstrap-vue/src/index.js')

    if (config.plugins.has('prefetch')) {
      config.plugin('prefetch').tap(options => {
        options[0].fileBlacklist = options[0].fileBlacklist || []
        options[0].fileBlacklist.push(/api-doc(.)+?\.js$/)
        return options
      })
    }
  },
  transpileDependencies: ['bootstrap-vue']
}
