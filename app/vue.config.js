const CompressionPlugin = require('compression-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')
const WorkboxPlugin = require('workbox-webpack-plugin')

module.exports = {
  lintOnSave: false,
  productionSourceMap: false,
  devServer: {
    disableHostCheck: true
  },
  configureWebpack: config => {
    if (!process.env.VUE_CLI_MODERN_BUILD) {
      config.entry.app.unshift('whatwg-fetch', 'abortcontroller-polyfill', 'intersection-observer')
    }

    config.plugins.push(new CopyWebpackPlugin({
      patterns: [
        { from: 'src/assets/images/arch(icon|logo).svg', to: 'img/[name].[ext]' }
      ]
    }))

    if ((process.env.VUE_CLI_MODERN_MODE && process.env.VUE_CLI_MODERN_BUILD) || !process.env.VUE_CLI_MODERN_MODE) {
      config.plugins.push(new WorkboxPlugin.GenerateSW({
        cacheId: 'app',
        exclude: [/robots\.txt$/],
        cleanupOutdatedCaches: true,
        dontCacheBustURLsMatching: /\.[a-f0-9]+\./,
        navigateFallback: '/index.html',
        navigateFallbackAllowlist: [
          new RegExp('^/compare/packages$'),
          new RegExp('^/fun$'),
          new RegExp('^/impressum$'),
          new RegExp('^/packages/[^/]+$'),
          new RegExp('^/packages$'),
          new RegExp('^/privacy-policy$'),
          new RegExp('^/$'),
          new RegExp('^/api/doc$')
        ],
        runtimeCaching: [
          {
            urlPattern: new RegExp('^https?://[^/]+/api/'),
            handler: 'StaleWhileRevalidate',
            options: { cacheName: 'api', expiration: { maxAgeSeconds: 24 * 60 * 60, maxEntries: 512 } }
          }
        ]
      }))
    }

    if (process.env.NODE_ENV === 'production') {
      config.plugins.push(new CompressionPlugin({ filename: '[path][base].gz', algorithm: 'gzip' }))
      config.plugins.push(new CompressionPlugin({ filename: '[path][base].br', algorithm: 'brotliCompress' }))
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
