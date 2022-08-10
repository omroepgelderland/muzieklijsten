const path = require('path');
const fs = require('fs');
var webpack = require('webpack');

module.exports = {
	'entry': {
		'admin': path.resolve(__dirname, 'src/js/admin.js'),
		'fbshare': path.resolve(__dirname, 'src/js/fbshare.js'),
		'los_toevoegen': path.resolve(__dirname, 'src/js/los_toevoegen.js'),
		'muzieklijst': path.resolve(__dirname, 'src/js/muzieklijst.js'),
		'resultaten': path.resolve(__dirname, 'src/js/resultaten.js')
	},
	'output': {
		'path': path.resolve(__dirname, 'public'),
		'filename': 'js/[name].js'
	},
	'module': {
		'rules': [
			{
				'test': /\.(jpe?g|png|gif|webp|svg)$/,
				'type': 'asset/resource',
				'generator': {
					'filename': 'afbeeldingen/[name][ext][query]'
				}
			},
			{
				'test': /\.(woff(2)?|ttf|eot)(\?v=\d+\.\d+\.\d+)?$/,
				'type': 'asset/resource',
				'generator': {
					'filename': 'fonts/[name]-[contenthash][ext][query]'
				}
			}
		]
	},
	'plugins': [
		new webpack.ProvidePlugin({
			'$': 'jquery',
			'jQuery': 'jquery',
			'moment': 'moment'
		})
	]
};
