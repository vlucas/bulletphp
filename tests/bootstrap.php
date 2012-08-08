<?php
/**
 * Path trickery ensures test suite will always run, standalone or within
 * another composer package. Designed to find composer autoloader and require
 */

$vendorPos = strpos(__DIR__, 'vendor/vlucas/bulletphp');
if($vendorPos !== false) {
  // Package has been cloned within another composer package, resolve path to autoloader
  $vendorDir = substr(__DIR__, 0, $vendorPos) . 'vendor/';
  echo "\n\n" . $vendorDir . "\n\n";
  $loader = require $vendorDir . 'autoload.php';
} else {
  // Package itself (cloned standalone)
  $loader = require __DIR__.'/../vendor/autoload.php';
}

// Add path for bullet tests
$loader->add('Bullet\Tests', __DIR__);
