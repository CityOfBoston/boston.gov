// Initialize modules
// Importing specific gulp API functions lets us write them below as series() instead of gulp.series()
const { src, dest, watch, series, parallel } = require('gulp');
// Importing all the Gulp-related packages we want to use
const sourcemaps = require('gulp-sourcemaps');
const babel = require('gulp-babel');
const webpack = require('webpack-stream');
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const cleanCSS = require('gulp-clean-css');
const sass = require('gulp-sass');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');
var replace = require('gulp-replace');
var glob = require("glob");


// File paths
const files = {
    cssPath: 'src/css/styles.css', 
    scssPath: 'src/js/components/**/*.scss',
    jsConfig: 'src/js/app/config.js',
    jsComponents: 'src/js/components/*.js',
    //jsReactApp: 'src/js/app/main.js',
}

// Sass task: compiles the style.scss file into style.css
function scssTask(){    
    return src(files.scssPath)
        .pipe(sourcemaps.init()) // initialize sourcemaps first
        .pipe(sass()) // compile SCSS to CSS
        .pipe(postcss([ autoprefixer(), cssnano() ])) // PostCSS plugins
        .pipe(sourcemaps.write('.')) // write sourcemaps file in current directory
        .pipe(dest('dist')
    ); // put final CSS in dist folder
}

// CSS task: minifies specified style sheet
function cssTask(){    
    return src(files.cssPath)
        .pipe(cleanCSS({compatibility: 'ie11'}))
        .pipe(concat('styles.css'))
        .pipe(dest('dist')
    ); // put final CSS in dist folder
}

// JS task: concatenates, uglifies, and transpiles JS files to index.js
function jsTask(){
    return src(glob.sync('./src/js/components/*.js'))
        //.pipe(babel())
        .pipe(webpack({
            watch: false,
            entry: glob.sync('./src/js/components/*.js'),
            output: {
                filename: 'index.js',
                libraryTarget: 'umd',
                libraryExport: 'default' //<-- New line
             },
            /*optimization: {
                splitChunks: {
                    maxSize: 10000,
                    chunks: 'all',
                },
            },*/
            mode: 'production',
            module: {
              rules: [
                {loader: 'babel-loader',options: {presets: ['@babel/preset-env']},exclude: /node_modules/},
              ],
            },
        }))
        //.pipe(concat('index.js'))
        .pipe(uglify())
        .pipe(dest('dist/')
    );
}

// Cachebust
function cacheBustTask(){
    var cbString = new Date().getTime();
    return src(['../../bos_web_app.libraries.yml'])
        .pipe(replace(/abutters.\d+/g, 'abutters.' + cbString))
        .pipe(dest('../../'));
}

// Watch task: watch SCSS and JS files for changes
// If any change, run scss and js tasks simultaneously
function watchTask(){
    watch([files.cssPath, files.scssPath, files.jsConfig, files.jsComponents],
        {interval: 1000, usePolling: true}, //Makes docker work
        series(
            parallel(cssTask, scssTask, jsTask),
            cacheBustTask
        )
    );    
}

// Export the default Gulp task so it can be run
// Runs the scss and js tasks simultaneously
// then runs cacheBust, then watch task
exports.default = series(
    parallel(cssTask, scssTask, jsTask), 
    cacheBustTask,
    watchTask
);