<?php

// Setup config file for running integration tests from ezplatform-kernel dependency
$file = __DIR__ . '/../vendor/ezsystems/ezplatform-kernel/config.php';
if (!file_exists($file) && !symlink("{$file}-DEVELOPMENT", $file)) {
    throw new RuntimeException('Could not symlink config.php-DEVELOPMENT to config.php');
}

$file = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies using composer to run the test suite.');
}

$autoload = require_once $file;