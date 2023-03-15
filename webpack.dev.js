const { merge } = require('webpack-merge');
const common = require('./webpack.common.js');

module.exports = merge(common, {
	'mode': 'development',
	'devtool': 'inline-source-map',
	'module': {
		'rules': [
			{
				'test': /\.js$/i,
				'exclude': /node_modules/,
				'use': {
					'loader': 'babel-loader',
					'options': {
						'presets': [
							['@babel/preset-env', {
								'targets': {
									'chrome': '110'
								}
							}]
						]
					}
				}
			},
			{
				'test': /\.css$/i,
				'use': [
					'style-loader',
					'css-loader'
				],
			},
			{
				'test': /\.s[ac]ss$/i,
				'use': [
					'style-loader',
					'css-loader',
					'sass-loader'
				],
			}
		]
	}
});
