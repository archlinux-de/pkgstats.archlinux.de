module.exports = {
  root: true,
  env: {
    node: true,
    browser: true
  },
  extends: [
    'eslint:recommended',
    'plugin:vue/essential',
    '@vue/standard',
    'plugin:compat/recommended'
  ],
  parserOptions: {
    parser: 'babel-eslint'
  },
  overrides: [
    {
      files: [
        '**/*.test.js'
      ],
      env: {
        jest: true
      }
    }
  ]
}
