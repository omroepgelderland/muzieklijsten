import * as webpack from "webpack";
import { merge } from "webpack-merge";
import * as sass from "sass";

import webpack_common from "./webpack.common.ts";

const dev_config: webpack.Configuration = {
  mode: "development",
  devtool: "inline-source-map",
  module: {
    rules: [
      {
        test: /\.(js|ts)$/i,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader",
          options: {
            presets: [
              [
                "@babel/preset-env",
                {
                  targets:
                    "last 2 Chrome versions, last 2 ChromeAndroid versions",
                },
              ],
              "@babel/preset-typescript",
            ],
          },
        },
      },
      {
        test: /\.css$/i,
        use: ["style-loader", "css-loader"],
      },
      {
        test: /\.s[ac]ss$/i,
        use: [
          "style-loader",
          "css-loader",
          {
            loader: "sass-loader",
            options: {
              sassOptions: {
                importers: [new sass.NodePackageImporter()],
                quietDeps: true,
              },
            },
          },
        ],
      },
    ],
  },
};

export default merge(webpack_common, dev_config);
