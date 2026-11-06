function htmlPage(name, filename) {
  filename ??= name;
  return new HtmlWebpackPlugin({
    ...html_plugin_conf,
    filename: `${filename}.html`,
    chunks: [name],
    template: path.resolve(__dirname, `src/frontend/pages/${name}/index.html`),
  });
}

const path = require("path");
const fs = require("fs");
var webpack = require("webpack");
const HtmlWebpackPlugin = require("html-webpack-plugin");
const html_plugin_conf = require("./webpack.html_plugin_conf.js");

module.exports = {
  entry: {
    admin: path.resolve(__dirname, "src/frontend/pages/admin/index.ts"),
    fbshare: path.resolve(__dirname, "src/frontend/pages/fbshare/index.js"),
    los_toevoegen: path.resolve(
      __dirname,
      "src/frontend/pages/los_toevoegen/index.js",
    ),
    "mod-vrijekeuzes": path.resolve(
      __dirname,
      "src/frontend/pages/mod-vrijekeuzes/index.ts",
    ),
    muzieklijst: path.resolve(
      __dirname,
      "src/frontend/pages/muzieklijst/index.js",
    ),
  },
  output: {
    path: path.resolve(__dirname, "public"),
    filename: "js/[name].js",
    clean: {
      keep: /\.php$/i,
    },
  },
  module: {
    rules: [
      {
        test: /\.html$/,
        use: {
          loader: "html-loader",
        },
      },
      {
        test: /\.(jpe?g|png|gif|webp|svg)$/,
        type: "asset/resource",
        generator: {
          filename: "afbeeldingen/[name][ext][query]",
        },
      },
      {
        test: /\.(woff(2)?|ttf|eot)(\?v=\d+\.\d+\.\d+)?$/,
        type: "asset/resource",
        generator: {
          filename: "fonts/[name]-[contenthash][ext][query]",
        },
      },
    ],
  },
  plugins: [
    htmlPage("admin"),
    htmlPage("los_toevoegen"),
    htmlPage("mod-vrijekeuzes"),
    htmlPage("muzieklijst", "index"),
  ],
  optimization: {
    runtimeChunk: "single",
    splitChunks: {
      chunks: "all",
      name: (module, chunks, cacheGroupKey) => {
        return chunks.map((chunk) => chunk.name).join("-");
      },
    },
  },
  resolve: {
    extensions: [".ts", ".tsc", "..."],
    alias: {
      "@muzieklijsten": path.resolve(__dirname, "src/frontend/shared"),
      "@styles": path.resolve(__dirname, "src/frontend/styles"),
    },
  },
  performance: {
    maxEntrypointSize: 512000,
    maxAssetSize: 512000,
  },
};
