const { VueLoaderPlugin } = require('vue-loader')
const CopyPlugin = require('copy-webpack-plugin')
const HtmlPlugin = require('html-webpack-plugin')
const webpack = require('webpack')
const path = require('path')

module.exports = {
  entry: { app: ['./src/main.js'] },
  output: { publicPath: '/' },
  resolve: {
    extensions: ['.js', '.vue'],
    alias: {
      '~': path.resolve(__dirname, './src/'),
      svgmap: path.resolve(__dirname, './node_modules/svgmap')
    }
  },

  module: {
    rules: [
      { test: /\.vue$/, loader: 'vue-loader' },
      { test: /\.svg$/, type: 'asset/resource', generator: { filename: 'img/[name].[contenthash].[ext]' } },
      { resourceQuery: /raw/, type: 'asset/source' },
      {
        test: /\.(webp|png|jpe?g)$/,
        loader: 'file-loader',
        options: {
          name: '[name].[contenthash].[ext]',
          outputPath: 'img'
        }
      }
    ]
  },

  plugins: [
    new webpack.DefinePlugin({
      __VUE_OPTIONS_API__: false,
      __VUE_PROD_DEVTOOLS__: false,
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false
    }),
    new VueLoaderPlugin(),
    new CopyPlugin({
      patterns: [
        { from: 'public', globOptions: { ignore: ['**/index.html'] } },
        { from: 'src/assets/images/arch(icon|logo).svg', to: 'img/[name][ext]' }
      ]
    }),
    new HtmlPlugin({
      template: 'public/index.html',
      title: process.env.npm_package_name
    })
  ]
}
