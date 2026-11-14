const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'js/consent': path.resolve( __dirname, 'src/js/consent/index.js' ),
		'js/frontend': path.resolve( __dirname, 'src/js/frontend/index.js' ),
	},
};
