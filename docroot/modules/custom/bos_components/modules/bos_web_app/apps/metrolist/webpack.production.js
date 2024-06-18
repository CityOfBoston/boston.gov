const merge = require( 'webpack-merge' );
const developmentConfig = require( './webpack.config.js' );

module.exports = merge( developmentConfig, {
  "output": {
    "publicPath": "/modules/custom/bos_components/modules/bos_web_app/apps/metrolist/",
  },
  "module": {
    "rules": [
      {
        "test": /\.js$/,
        "loader": 'string-replace-loader',
        "options": {
          "multiple": [
            {
              "search": /(["'`])\/images\/([^\1]*)(\1)/g,
              "replace": '$1https://assets.boston.gov/icons/metrolist/$2$3',
            },
          ],
        },
      },
    ],
  },
  "mode": "production",
} );
