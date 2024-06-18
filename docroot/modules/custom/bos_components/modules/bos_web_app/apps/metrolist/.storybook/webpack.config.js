const path = require( 'path' );
const rootWebpackConfig = require( '../webpack.config' );
const CopyPlugin = require( 'copy-webpack-plugin' );

// Delete everything except "resolve" and "module"
delete rootWebpackConfig.entry;
delete rootWebpackConfig.output;
delete rootWebpackConfig.mode;
delete rootWebpackConfig.optimization;
delete rootWebpackConfig.plugins;

module.exports = {
  ...rootWebpackConfig,
};