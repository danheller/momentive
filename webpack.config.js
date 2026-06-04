const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'impact-stat/index': './blocks/impact-stat/src/index.js',
		'impact-stat/view':  './blocks/impact-stat/src/view.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'blocks/build'),
	},
};