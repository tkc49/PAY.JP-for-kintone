#!/bin/sh
zip -r ../ht-payjp-for-kintone-pro.zip ./ -x ./bin\* -x ./tests\* -x *.git* -x '.distignore' -x '.editorconfig' -x '.gitignore' -x '.phpcs.xml.dist' -x '.travis.yml' -x 'composer.json' -x 'composer.lock' -x 'Gruntfile.js' -x 'phpunit.xml.dist' -x 'zip.sh' -x 'copy-to-wordpress-plugin-directory.sh'
