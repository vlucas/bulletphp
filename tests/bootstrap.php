<?php
error_reporting(-1);
date_default_timezone_set('UTC');

$loader = require __DIR__.'/../vendor/autoload.php';

// Add path for bullet tests
$loader->add('Bullet\Tests', __DIR__);
