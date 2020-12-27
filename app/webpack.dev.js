module.exports = {
  mode: 'development',
  target: 'web', // Workaround for https://github.com/webpack/webpack-dev-server/issues/2758
  devtool: 'inline-source-map',

  module: {
    rules: [
      { test: /\.s?css$/, use: ['style-loader', 'css-loader', 'postcss-loader', 'sass-loader'] }
    ]
  },

  devServer: {
    historyApiFallback: { disableDotRule: true }
  },

  watchOptions: {
    ignored: 'node_modules'
  }
}
