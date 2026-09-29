/**
 * Builds the admin app (onboarding wizard and settings screen) and the block
 * editor scripts into build/.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'client/admin/index.js' ),
		blocks: path.resolve( __dirname, 'client/blocks/index.js' ),
	},
};
