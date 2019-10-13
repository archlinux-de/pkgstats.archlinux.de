module.exports = {
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/assets/$1'
  },
  transform: {
    '\\.js$': [require.resolve('babel-jest'), { 'presets': ['@babel/preset-env'] }]
  }
}
