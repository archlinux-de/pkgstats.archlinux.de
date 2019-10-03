const Encore = require('@symfony/webpack-encore')
const CompressionPlugin = require('compression-webpack-plugin')

Encore
  .setOutputPath((process.env.PUBLIC_PATH || 'public') + '/build')
  .setPublicPath('/build')
  .addEntry('js/app', './assets/js/app.js')
  .addEntry('js/swagger_ui', './assets/js/swagger_ui.js')
  .addEntry('js/packages', './assets/js/packages.js')
  .addEntry('js/package', './assets/js/package.js')
  .addEntry('js/compare', './assets/js/compare.js')
  .addEntry('js/fun', './assets/js/fun.js')
  .addStyleEntry('css/app', './assets/css/app.scss')
  .addStyleEntry('css/chartist', './assets/css/chartist.scss')
  .addStyleEntry('css/swagger_ui', './assets/css/swagger_ui.scss')
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
  .enableVueLoader()
  .autoProvidejQuery()
  .autoProvideVariables({
    Popper: 'popper.js'
  })
  .configureBabel(() => {}, {
    useBuiltIns: 'usage',
    corejs: 3
  })

if (Encore.isProduction()) {
  Encore.addPlugin(new CompressionPlugin())
} else {
  Encore.cleanupOutputBeforeBuild()
}

module.exports = Encore.getWebpackConfig()
