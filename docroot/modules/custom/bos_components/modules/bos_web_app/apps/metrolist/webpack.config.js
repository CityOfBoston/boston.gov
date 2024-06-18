require( 'dotenv' ).config();
const Dotenv = require( 'dotenv-webpack' );
const webpack = require( 'webpack' );
const path = require( 'path' );
const fs = require( 'fs' );

const HtmlWebpackPlugin = require( 'html-webpack-plugin' );
const CopyPlugin = require( 'copy-webpack-plugin' );

module.exports = {
  "entry": "./src/index.js",
  "output": {
    "path": path.resolve( __dirname, "dist" ),
    "publicPath": '/',
    "filename": "index.bundle.js",
  },
  "resolve": {
    "alias": {
      "@patterns": path.resolve( __dirname, 'patterns' ),
      "@util": path.resolve( __dirname, 'src/util' ),
      "@globals": path.resolve( __dirname, 'src/globals' ),
      "@components": path.resolve( __dirname, 'src/components' ),
      "__mocks__": path.resolve( __dirname, '__mocks__' ),
    },
  },
  "module": {
    // "loaders": [
    //   { "test": /\.styl$/, "loader": "style-loader!css-loader!stylus-loader" },
    // ],
    "rules": [
      {
        "test": /\.js$/,
        "use": "babel-loader",
        // "use": "raw-loader",
      },
      {
        "test": /\.css$/,
        "use": ["style-loader", "css-loader"],
      },
      {
        "test": /\.s[ac]ss$/i,
        "use": [
          "style-loader",
          // "css-loader",
          "postcss-loader",
          "sass-loader",
          // {
          //   "loader": "sass-loader",
          //   "options": {
          //     "sassOptions": {
          //       "includePaths": ["src/sass"],
          //     },
          //   },
          // },
        ],
      },
      {
        "test": /\.(svg|webp|png|jpe?g|gif)$/,
        "use": "file-loader",
      },
       {
         "test": /\.js$/,
         "loader": 'string-replace-loader',
         "options": {
           "search": /\/images\/(.*)/g,
           "replace": 'https://assets.boston.gov/icons/metrolist/$1',
         },
       },
    ],
  },
  "mode": "development",
  "devServer": {
    "historyApiFallback": true,
  },
  // "devtool": "eval-source-map",
  // "devtool": "inline-source-map",
  "devtool": false,
  "optimization": {
    "nodeEnv": false,
  },
  "plugins": [
    /*new HtmlWebpackPlugin( {
      "template": "public/index.html",
    } )*/
    new Dotenv(),
    new CopyPlugin( [
      { "from": "public" },
    ] ),
    new webpack.DefinePlugin( {
      "process.env.SITE_TITLE": JSON.stringify( process.env.SITE_TITLE ),
      "process.env.DOMAIN_TITLE": JSON.stringify( process.env.DOMAIN_TITLE ),
    } ),
  ],
};
