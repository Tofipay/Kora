#!/bin/sh
# Rebuild the pre-minified assets after editing css/js sources.
# asset_min_url() serves the .min files only while they are NEWER than the
# sources, so forgetting to run this can never ship stale code.
cd "$(dirname "$0")/../public/assets" || exit 1
npx -y esbuild css/app.css --minify --outfile=css/app.min.css
npx -y esbuild js/app.js --minify --target=es2018 --outfile=js/app.min.js
npx -y esbuild js/api-service.js --minify --target=es2018 --outfile=js/api-service.min.js
npx -y esbuild js/watch.js --minify --target=es2018 --outfile=js/watch.min.js
