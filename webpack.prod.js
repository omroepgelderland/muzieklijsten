const { merge } = require('webpack-merge');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const common = require('./webpack.common.js');

module.exports = merge(common, {
	'mode': 'production',
	'module': {
		'rules': [
			{
				'test': /\.js$/i,
				'exclude': /node_modules/,
				'use': {
					'loader': 'babel-loader'
				}
			},
			{
				'test': /\.css$/i,
				'use': [
					{
						'loader': MiniCssExtractPlugin.loader,
						'options': {
							'publicPath': '../'
						}
					},
					'css-loader'
				],
			},
			{
				'test': /\.s[ac]ss$/i,
				'use': [
					{
						'loader': MiniCssExtractPlugin.loader,
						'options': {
							'publicPath': '../'
						}
					},
					'css-loader',
					'sass-loader'
				],
			}
		]
	},
	'plugins': [
		new MiniCssExtractPlugin({
			'filename': 'css/[name].css',
			'chunkFilename': '[id].css'
		})
	],
	'optimization': {
		'runtimeChunk': 'single',
		'splitChunks': {
			'chunks': 'all'
		},
		'minimize': true,
		'minimizer': [
			new TerserPlugin({
				'extractComments': false
			}),
			new CssMinimizerPlugin()
		]
	}
});
