import * as webpack from "webpack";
import * as path from "path";
import * as url from "url";
import HtmlWebpackPlugin from "html-webpack-plugin";

import html_plugin_conf from "./webpack.html_plugin_conf.ts";

const __filename = url.fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function htmlPage(name: string, filename?: string) {
  filename ??= name;
  return new HtmlWebpackPlugin({
    ...html_plugin_conf,
    filename: `${filename}.html`,
    chunks: [name],
    template: path.resolve(__dirname, `src/frontend/pages/${name}/index.html`),
  });
}

const config: webpack.Configuration = {
  entry: {
    admin: path.resolve(__dirname, "src/frontend/pages/admin/index.ts"),
    fbshare: path.resolve(__dirname, "src/frontend/pages/fbshare/index.ts"),
    los_toevoegen: path.resolve(
      __dirname,
      "src/frontend/pages/los-toevoegen/index.ts",
    ),
    "mod-vrijekeuzes": path.resolve(
      __dirname,
      "src/frontend/pages/mod-vrijekeuzes/index.ts",
    ),
    muzieklijst: path.resolve(
      __dirname,
      "src/frontend/pages/muzieklijst/index.ts",
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
    htmlPage("los-toevoegen"),
    htmlPage("mod-vrijekeuzes"),
    htmlPage("muzieklijst", "index"),
  ],
  optimization: {
    runtimeChunk: "single",
    splitChunks: {
      chunks: "all",
      name: (module, chunks) => {
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

export default config;
