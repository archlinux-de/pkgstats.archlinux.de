/* eslint-disable prefer-regex-literals */
const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const CompressionPlugin = require('compression-webpack-plugin')
const WorkboxPlugin = require('workbox-webpack-plugin')
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  mode: 'production',
  output: { path: path.resolve(__dirname, 'dist'), filename: 'js/[name].[contenthash].js' },

  module: {
    rules: [
      { test: /\.js$/, loader: 'babel-loader', exclude: /node_modules/ },
      { test: /\.s?css$/, use: [MiniCssExtractPlugin.loader, 'css-loader', 'postcss-loader', 'sass-loader'] }
    ]
  },

  plugins: [
    new MiniCssExtractPlugin({
      filename: 'css/[name].[contenthash].css',
      chunkFilename: 'css/[name].[contenthash].css'
    }),
    new WorkboxPlugin.GenerateSW({
      cacheId: 'app',
      exclude: [/robots\.txt$/],
      cleanupOutdatedCaches: true,
      dontCacheBustURLsMatching: /\.[a-f0-9]+\./,
      navigateFallback: '/index.html',
      navigateFallbackAllowlist: [
        new RegExp('^/compare/packages$'),
        new RegExp('^/compare/system-architectures/[^/]+$'),
        new RegExp('^/countries$'),
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
    }),
    new CompressionPlugin({ filename: '[path][base].gz', algorithm: 'gzip' }),
    new CompressionPlugin({ filename: '[path][base].br', algorithm: 'brotliCompress' })
  ],

  optimization: {
    splitChunks: { chunks: 'all' },
    minimizer: [
      new TerserPlugin({ terserOptions: { format: { comments: false } }, extractComments: false }),
      new CssMinimizerPlugin({ minimizerOptions: { preset: ['default', { discardComments: { removeAll: true } }] } })
    ]
  }
}
