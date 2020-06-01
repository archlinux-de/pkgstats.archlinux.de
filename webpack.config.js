const Encore = require('@symfony/webpack-encore')
const CompressionPlugin = require('compression-webpack-plugin')
const path = require('path')

Encore
  .setOutputPath((process.env.PUBLIC_PATH || 'public') + '/build')
  .setPublicPath('/build')
  .addAliases({ '@': path.resolve(__dirname, 'assets') })
  .addAliases({ 'bootstrap-vue$': 'bootstrap-vue/src/index.js' })
  .addEntry('main', '@/js/main.js')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .enableSassLoader()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .enablePostCssLoader()
  .enableVueLoader(() => {}, { runtimeCompilerBuild: false })
  .configureBabel(() => {}, { useBuiltIns: 'usage', corejs: 3, includeNodeModules: ['bootstrap-vue'] })

if (Encore.isProduction()) {
  Encore.addPlugin(new CompressionPlugin({ filename: '[path].gz[query]', algorithm: 'gzip' }))
  Encore.addPlugin(new CompressionPlugin({ filename: '[path].br[query]', algorithm: 'brotliCompress' }))
} else {
  Encore.cleanupOutputBeforeBuild()
}

module.exports = Encore.getWebpackConfig()
