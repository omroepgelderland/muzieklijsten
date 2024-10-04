const path = require('path');
const fs = require('fs');
var webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = {
  entry: {
    admin: path.resolve(__dirname, 'src/js/admin.js'),
    fbshare: path.resolve(__dirname, 'src/js/fbshare.js'),
    los_toevoegen: path.resolve(__dirname, 'src/js/los_toevoegen.js'),
    muzieklijst: path.resolve(__dirname, 'src/js/muzieklijst.js')
  },
  output: {
    path: path.resolve(__dirname, 'public'),
    filename: 'js/[name].js'
  },
  module: {
    rules: [
      {
        test: /\.html$/,
        use: {
          loader: 'html-loader'
        }
      },
      {
        test: /\.(jpe?g|png|gif|webp|svg)$/,
        type: 'asset/resource',
        generator: {
          filename: 'afbeeldingen/[name][ext][query]'
        }
      },
      {
        test: /\.(woff(2)?|ttf|eot)(\?v=\d+\.\d+\.\d+)?$/,
        type: 'asset/resource',
        generator: {
          filename: 'fonts/[name]-[contenthash][ext][query]'
        }
      }
    ]
  },
  plugins: [
    new webpack.ProvidePlugin({
      '$': 'jquery',
      jQuery: 'jquery',
      moment: 'moment'
    }),
    new HtmlWebpackPlugin({
      filename: 'index.html',
      chunks: ['muzieklijst'],
      template: path.resolve(__dirname, 'src', 'html', 'muzieklijst.html')
    }),
    new HtmlWebpackPlugin({
      filename: 'los_toevoegen.html',
      chunks: ['los_toevoegen'],
      template: path.resolve(__dirname, 'src', 'html', 'los_toevoegen.html')
    }),
    new HtmlWebpackPlugin({
      filename: 'admin.html',
      chunks: ['admin'],
      template: path.resolve(__dirname, 'src', 'html', 'admin.html')
    })
  ],
  optimization: {
    runtimeChunk: 'single',
    splitChunks: {
      chunks: 'all',
      name: (module, chunks, cacheGroupKey) => {
        return chunks.map((chunk) => chunk.name).join('-');
      }
    }
  }
};
