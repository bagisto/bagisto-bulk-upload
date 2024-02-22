const path = require('path');
const mix = require('laravel-mix');

require('laravel-mix-merge-manifest');
require('laravel-mix-clean');

const prodPublicPath = path.join('publishable', 'assets');
const devPublicPath = path.join(
    '..',
    '..',
    '..',
    'public',
    'vendor',
    'webkul',
    'admin',
    'assets'
);
const publicPath = mix.inProduction() ? prodPublicPath : devPublicPath;

console.log(`Assets will be published in: ${publicPath}`);

const assetsPath = path.join(__dirname, 'src', 'Resources', 'assets');
const jsPath = path.join(assetsPath, 'js');
const imagesPath = path.join(assetsPath, 'images');
const sampleFilePath = path.join(assetsPath, 'sample-files');

mix.setPublicPath(publicPath)
    .js(path.join(jsPath, 'app.js'), 'js/bulk-upload-app.js')

    .copy(imagesPath, path.join(publicPath, 'images'))

    .copy(sampleFilePath, path.join(publicPath, 'sample-files'))

    .sass(path.join(assetsPath, 'sass', 'admin.scss'), 'css/bulk-upload-admin.css')

    .clean({
        cleanOnceBeforeBuildPatterns: [
            'js/**/*',
            'css/bulk-upload-admin.css',
        ],
    })

    .options({
        processCssUrls: false,
        clearConsole: mix.inProduction(),
    })

    .disableNotifications()
    .mergeManifest();

if (mix.inProduction()) {
    mix.version();
}
