const purgecss = require('@fullhuman/postcss-purgecss')
const autoprefixer = require('autoprefixer')

module.exports = {
  plugins: [
    autoprefixer,
    purgecss({
      content: ['**/*.js', '**/*.html', '**/*.vue'],
      skippedContentGlobs: ['node_modules/**', 'tests/**'],
      variables: true
    })
  ]
}
