module.exports = {
  mode: 'development',
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
