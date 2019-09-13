const Encore = require('@symfony/webpack-encore')
const CompressionPlugin = require('compression-webpack-plugin')

Encore
  .setOutputPath((process.env.PUBLIC_PATH || 'public') + '/build')
  .setPublicPath('/build')
  .cleanupOutputBeforeBuild()
  .addEntry('js/app', './assets/js/app.js')
  .addEntry('js/swagger', './assets/js/swagger.js')
  .addEntry('js/package', './assets/js/package.js')
  .addStyleEntry('css/app', './assets/css/app.scss')
  .addStyleEntry('css/swagger', './assets/css/swagger.scss')
  .copyFiles({
    from: 'assets/images',
    to: 'images/[path][name].[hash:8].[ext]'
  })
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .enableSassLoader()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .enablePostCssLoader()
  .autoProvidejQuery()
  .autoProvideVariables({
    Popper: 'popper.js'
  })

if (Encore.isProduction()) {
  Encore.addPlugin(new CompressionPlugin())
}

module.exports = Encore.getWebpackConfig()
