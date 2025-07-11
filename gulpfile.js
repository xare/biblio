var { watch, src, dest, series } = require('gulp');
var rename = require('gulp-rename');
var sass = require('gulp-sass')(require('sass'));
var autoprefixer = require('gulp-autoprefixer');
var sourcemaps = require('gulp-sourcemaps');
var browserify = require('browserify');
var babelify = require('babelify');
var source = require('vinyl-source-stream');
var buffer = require('vinyl-buffer');
var uglify = require('gulp-uglify');
var browserSync = require('browser-sync').create();

var projectURL = 'http://larepartidora';

var styleBiblio = './assets/scss/biblio.scss';
var styleCovers = './assets/scss/covers.scss';
var styleCoversAdmin = './assets/scss/coversAdmin.scss';
var styleGeslibAdmin = './assets/scss/geslibAdmin.scss';

var styleFiles = [styleBiblio, styleCovers, styleCoversAdmin, styleGeslibAdmin];
var styleDIST = './dist/css/';
var styleURL = './dist/css/';
var mapURL = '../maps';

var jsSRC = './assets/js/';
var jsAdmin = 'biblio.js';
var jsCovers = 'covers.js';
var jsCoversAdmin = 'coversAdmin.js';
var jsGeslibAdmin = 'geslibAdmin.js';
var jsGeslibUpdateValues = 'geslibUpdateValues.js';
var jsPagination = 'pagination.js';
var jsFiles = [jsAdmin, jsCovers, jsCoversAdmin, jsGeslibAdmin, jsGeslibUpdateValues, jsPagination];
var jsURL = './dist/js/';
var jsFolder = 'assets/js/';
var jsDIST = './dist/js/';

var htmlFile = 'index.html';

var styleWatch = './assets/scss/**/*.scss';
var jsWatch = './assets/js/**/*.js';
var phpWatch = '**/*.php';

function style() {
    // compile
    return src(styleFiles)
        .pipe(sourcemaps.init())
        .pipe(sass({
            errorLogToConsole: true,
            outputStyle: 'compressed'
        }))
        .on('error', console.error.bind(console))
        .pipe(autoprefixer({
            overrideBrowserslist: ['last 2 versions'],
            cascade: false
        }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write(mapURL))
        .pipe(dest(styleURL))
        .pipe(browserSync.stream());
}

async function js() {
    jsFiles.map(function(entry) {
        return browserify({
                entries: [jsSRC + entry]
            })
            .transform(babelify, { presets: ['@babel/preset-env'] })
            .bundle()
            .pipe(source(entry))
            .pipe(rename({ extname: '.min.js' }))
            .pipe(buffer())
            .pipe(sourcemaps.init({ loadMaps: true }))
            .pipe(uglify())
            .pipe(sourcemaps.write(mapURL))
            .pipe(dest(jsDIST))
            .pipe(browserSync.stream());
    });

    /* }); */
}

function browserSyncServe(cb) {
    browserSync.init({
        proxy: projectURL,
        open: false,
        injectChanges: true,
    });
    cb();
}

function browsersyncReload(cb) {
    browserSync.reload();
    cb();
}


function watchTask() {
    //watch(htmlWatch, browsersyncReload);
    watch(phpWatch, browsersyncReload);
    watch([styleWatch, jsWatch], series(style, js, browsersyncReload));
}
exports.default = series(
    style,
    js,
    browserSyncServe,
    watchTask
);