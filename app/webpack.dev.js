module.exports = {
  mode: 'development',
  devtool: 'inline-source-map',

  module: {
    rules: [
      { test: /\.s?css$/, use: ['style-loader', 'css-loader', 'postcss-loader', { loader: 'sass-loader', options: { sassOptions: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin'] } } }] }
    ]
  },

  devServer: {
    historyApiFallback: { disableDotRule: true }
  },

  watchOptions: {
    ignored: 'node_modules'
  }
}
