/**
 * The default WordPress build (blocks found through their block.json), plus the editor
 * script.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		'editor/index': './src/editor/index.js',
	},
};
