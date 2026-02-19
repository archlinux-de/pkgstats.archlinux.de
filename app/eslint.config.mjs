import { defineConfig } from 'eslint/config'
import js from '@eslint/js'
import neostandard from 'neostandard'
// @ts-ignore
import pluginCypress from 'eslint-plugin-cypress'
import pluginJest from 'eslint-plugin-jest'
import compat from 'eslint-plugin-compat'

export default defineConfig([
  { ignores: ['public', 'dist'] },
  js.configs.recommended,
  ...neostandard(),
  compat.configs['flat/recommended'],
  {
    files: ['tests/e2e/**/*.js'],
    ...pluginCypress.configs.recommended,
    rules: {
      'cypress/no-unnecessary-waiting': 'warn',
      'cypress/unsafe-to-chain-command': 'warn'
    }
  },
  {
    files: ['tests/unit/**/*.spec.js'],
    plugins: { jest: pluginJest },
    languageOptions: {
      globals: pluginJest.environments.globals.globals,
    },
  }
])
