#!/usr/bin/env php
<?php
if (class_exists('Phar')) {
    Phar::mapPhar('phpreflect.phar');
    Phar::interceptFileFuncs();

    if (!getenv("REFLECT")) {
        $home  = defined('PHP_WINDOWS_VERSION_BUILD') ? 'USERPROFILE' : 'HOME';
        $files = array(
            realpath('./phpreflect.json'),
            getenv($home).'/.config/phpreflect.json',
            '/etc/phpreflect.json',
        );
        foreach ($files as $file) {
            if (file_exists($file)) {
                putenv("REFLECT=$file");
                break;
            }
        }
    }
    require 'phar://' . __FILE__ . '/bin/phpreflect';
}
__HALT_COMPILER();