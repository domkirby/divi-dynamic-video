/**
 * webpack.config.js — Divi Video Post plugin
 *
 * Compiles the VideoEmbed JSX module into a single bundle that Divi's
 * DiviExtension PHP class enqueues inside the Visual Builder.
 *
 * React is declared as an external so the bundle uses the React copy that
 * WordPress (and Divi) already load on the page rather than bundling its own.
 */

const path = require( 'path' );

module.exports = {
	entry: './src/index.js',

	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: 'divi-video-post.min.js',
		// IIFE — no global exports required; registration happens inside the
		// entry file when the Divi builder fires its ready event.
		iife: true,
	},

	externals: {
		// Use the React that WordPress/Divi already exposes globally.
		react: 'React',
		'react-dom': 'ReactDOM',
	},

	module: {
		rules: [
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							[
								'@babel/preset-env',
								{
									// Target the browsers Divi itself supports.
									targets: '> 0.5%, last 2 versions, not dead, not IE 11',
									// Only import polyfills that are actually used.
									useBuiltIns: false,
								},
							],
							[
								'@babel/preset-react',
								{
									// Use the classic JSX transform so we can rely on the
									// external React global rather than auto-importing it.
									runtime: 'classic',
								},
							],
						],
					},
				},
			},
		],
	},

	resolve: {
		extensions: [ '.js', '.jsx' ],
	},

	// Keep the output readable enough for debugging in development mode;
	// production mode (yarn build) minifies automatically.
	devtool: process.env.NODE_ENV === 'production' ? false : 'source-map',
};
