const globals = require( 'globals' );
const wpConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );
const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [
			'**/*.min.js',
			'coverage/**',
			'playwright-report/**',
			'test-results/**',
			'test-reports/**',
		],
	},
	...wpConfig,
	{
		files: [ 'assets/js/**/*.js' ],
		languageOptions: {
			sourceType: 'script',
			globals: {
				...globals.browser,
				...globals.jquery,
				flw_pay_options: 'readonly',
				tinymce: 'readonly',
			},
		},
	},
	{
		// Provided by WordPress at runtime (see build/*.asset.php), so they are
		// externals rather than installed dependencies.
		files: [ 'client/**/*.js' ],
		settings: {
			'import/core-modules': [
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/primitives',
				'@wordpress/server-side-render',
			],
		},
	},
	...wpPlugin.configs[ 'test-playwright' ].map( ( config ) => ( {
		...config,
		files: [ 'tests/e2e/**/*.js', 'playwright.config.js' ],
		// The report script's own tests run in Vitest, not Playwright.
		ignores: [ 'tests/e2e/report/*.test.js' ],
	} ) ),
	{
		files: [ 'tests/e2e/**/*.js', 'playwright.config.js', '*.cjs' ],
		languageOptions: { globals: { ...globals.node } },
	},
];
