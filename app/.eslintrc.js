module.exports = {
  root: true,
  env: {
    browser: true
  },
  extends: [
    'standard',
    'plugin:vue/essential',
    'plugin:compat/recommended'
  ],
  settings: {
    polyfills: []
  },
  rules: {
    'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    'vue/multi-word-component-names': 'off'
  }
}
