module.exports = {
  moduleFileExtensions: ['js', 'json', 'vue', 'scss'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/assets/$1'
  },
  transform: {
    '\\.js$': [require.resolve('babel-jest'), { 'presets': ['@babel/preset-env'] }]
  }
}
