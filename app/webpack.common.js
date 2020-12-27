const VueLoaderPlugin = require('vue-loader/lib/plugin')
const CopyPlugin = require('copy-webpack-plugin')
const HtmlPlugin = require('html-webpack-plugin')

module.exports = {
  entry: { app: ['./src/main.js'] },
  output: { publicPath: '/' },
  resolve: {
    extensions: ['.js', '.vue']
  },

  module: {
    rules: [
      { test: /\.vue$/, loader: 'vue-loader' },
      { test: /\.svg$/, use: { loader: 'file-loader', options: { name: 'img/[name].[contenthash].[ext]' } } }
    ]
  },

  plugins: [
    new VueLoaderPlugin(),
    new CopyPlugin({
      patterns: [
        { from: 'public', globOptions: { ignore: ['**/index.html'] } },
        { from: 'src/assets/images/arch(icon|logo).svg', to: 'img/[name].[ext]' }
      ]
    }),
    new HtmlPlugin({
      template: 'public/index.html',
      title: process.env.npm_package_name
    })
  ]
}
