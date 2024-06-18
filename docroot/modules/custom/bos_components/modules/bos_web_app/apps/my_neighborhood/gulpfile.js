// Initialize modules
// Importing specific gulp API functions lets us write them below as series() instead of gulp.series()
const { src, dest, watch, series, parallel } = require('gulp');
// Importing all the Gulp-related packages we want to use
const sourcemaps = require('gulp-sourcemaps');
const babel = require('gulp-babel');
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const cleanCSS = require('gulp-clean-css');
const sass = require('gulp-sass')(require('sass'));
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');
var replace = require('gulp-replace');


// File paths
const files = {
    cssPath: 'src/css/mnl_styles.css',
    scssPath: 'src/components/**/*.scss',
    react: 'node_modules/react/umd/react.production.min.js',
    reactDom: 'node_modules/react-dom/umd/react-dom.production.min.js',
    jsReactConfig: 'src/js/app/mnl_config.js',
    jsReactComponents: 'src/js/components/*.js',
    jsReactApp: 'src/js/app/mnl_react.js',
}

// Sass task: compiles the style.scss file into style.css
function scssTask(){
    return src(files.scssPath)
        .pipe(sourcemaps.init()) // initialize sourcemaps first
        .pipe(sass()) // compile SCSS to CSS
        .pipe(postcss([ autoprefixer(), cssnano() ])) // PostCSS plugins
        .pipe(sourcemaps.write('.')) // write sourcemaps file in current directory
        .pipe(dest('dist')); // put final CSS in dist folder
}

// CSS task: minifies specified style sheet
function cssTask(){
    return src(files.cssPath)
        .pipe(cleanCSS({compatibility: 'ie11'}))
        .pipe(concat('mnl_styles.css'))
        .pipe(dest('dist')
    ); // put final CSS in dist folder
}

// JS task: concatenates, uglifies, and transpiles JS files to script.js
function jsTask(){
    return src([
        files.react,
        files.reactDom,
        files.jsReactConfig,
        files.jsReactComponents,
        files.jsReactApp
        //,'!' + 'includes/js/jquery.min.js', // to exclude any specific files
        ])
        .pipe(babel())
        .pipe(concat('index.js'))
        .pipe(uglify())
        .pipe(dest('dist')
    );
}

// Cachebust
function cacheBustTask(){
    var cbString = new Date().getTime();
    return src(['../../bos_web_app.libraries.yml'])
        .pipe(replace(/my_neighborhood.\d+/g, 'my_neighborhood.' + cbString))
        .pipe(dest('../../'));
}

// Watch task: watch SCSS and JS files for changes
// If any change, run scss and js tasks simultaneously
function watchTask(){
    watch([files.cssPath, files.scssPath, files.jsReactConfig, files.jsReactComponents, files.jsReactApp],
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
    // parallel(cssTask, scssTask, jsTask),
    parallel(cssTask, jsTask),
    cacheBustTask,
    watchTask
);
