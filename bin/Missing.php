#!/usr/bin/env php
<?php
foreach (array(__DIR__ . '/../../../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php') as $file) {
    if (file_exists($file)) {
        define('HEAD_COMPOSER_INSTALL', $file);
        break;
    }
}
unset($file);
if (!defined('HEAD_COMPOSER_INSTALL')) {
    fwrite(STDERR,
        'You need to set up the project dependencies using the following commands:' . PHP_EOL .
        'wget http://getcomposer.org/composer.phar' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
    die(1);
}
$loader = require HEAD_COMPOSER_INSTALL;
define('MISSING_VERSION', '1.0.0');
$app = new \whm\MissingRequest\Cli\Application();
$app->run();