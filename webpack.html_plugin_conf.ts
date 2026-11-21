import { env } from "process";

export default {
  hash: false,
  cache: false,
  minify: env.production
    ? {
        conservativeCollapse: true,
        keepClosingSlash: false,
        removeRedundantAttributes: false,
        removeScriptTypeAttributes: false,
        removeStyleLinkTypeAttributes: false,
      }
    : false,
};
