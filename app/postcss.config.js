const purgeImport = require('@fullhuman/postcss-purgecss')
const purgeCSSPlugin = purgeImport.purgeCSSPlugin || purgeImport.default || purgeImport
const autoprefixer = require('autoprefixer')

module.exports = {
  plugins: [
    autoprefixer,
    purgeCSSPlugin({
      content: ['**/*.js', '**/*.html', '**/*.vue'],
      skippedContentGlobs: ['node_modules/**', 'tests/**'],
      variables: true,
      safelist: {
        greedy: [/^svgMap-/],
        variables: ['--bs-primary']
      }
    })
  ]
}
