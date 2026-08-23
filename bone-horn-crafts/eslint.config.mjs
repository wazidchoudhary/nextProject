/**
 * ESLint configuration.
 *
 * The storefront JavaScript is plain ES modules with no framework and no
 * bundler, so this stays deliberately small: catch real mistakes, enforce the
 * few stylistic rules that matter for readability, and stay out of the way.
 */

import stylistic from '@stylistic/eslint-plugin';

export default [
	{
		files: [ 'wp-content/**/assets/js/**/*.js', 'tests/**/*.mjs', 'tools/**/*.mjs' ],
		languageOptions: {
			ecmaVersion: 2023,
			sourceType: 'module',
			globals: {
				// Browser surface the storefront modules actually touch.
				window: 'readonly',
				document: 'readonly',
				fetch: 'readonly',
				console: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				requestAnimationFrame: 'readonly',
				history: 'readonly',
				location: 'readonly',
				localStorage: 'readonly',
				sessionStorage: 'readonly',
				URL: 'readonly',
				URLSearchParams: 'readonly',
				FormData: 'readonly',
				CustomEvent: 'readonly',
				IntersectionObserver: 'readonly',
				PerformanceObserver: 'readonly',
				MutationObserver: 'readonly',
				HTMLElement: 'readonly',
				Node: 'readonly',
				AbortController: 'readonly',
				// Localised by wp_localize_script.
				bhcTheme: 'readonly',
				bhcStorefront: 'readonly',
				// Injected into the page by the accessibility suite.
				axe: 'readonly',
				// Node, for the test harnesses.
				process: 'readonly',
			},
		},
		plugins: { '@stylistic': stylistic },
		rules: {
			// Correctness.
			'no-undef': 'error',
			'no-unused-vars': [ 'error', { argsIgnorePattern: '^_' } ],
			'no-implicit-globals': 'error',
			eqeqeq: [ 'error', 'always' ],
			'no-var': 'error',
			'prefer-const': 'error',
			'no-console': 'off',

			// Readability. Matching the tab-indented, spaced-parens house style
			// the PHP already uses, so the two do not read as different projects.
			'@stylistic/indent': [ 'error', 'tab' ],
			'@stylistic/quotes': [ 'error', 'single', { avoidEscape: true } ],
			'@stylistic/semi': [ 'error', 'always' ],
			'@stylistic/comma-dangle': [ 'error', 'always-multiline' ],
			'@stylistic/space-in-parens': [ 'error', 'always' ],
			'@stylistic/array-bracket-spacing': [ 'error', 'always' ],
			'@stylistic/object-curly-spacing': [ 'error', 'always' ],
		},
	},
];
