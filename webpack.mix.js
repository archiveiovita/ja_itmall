let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/assets/js/app.js', 'public/fronts/js')
   .sass('resources/assets/sass/app.scss', 'public/fronts/css');

mix.config.fileLoaderDirs.fonts = 'public/fronts/fonts';

mix.sass('resources/assets/sass/compiled.scss', 'public/fronts/css');
