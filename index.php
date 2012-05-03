<?php
use \Rackem\Rack;

require __DIR__ . '/src/Bullet/App.php';
require 'vendor/autoload.php';

// Bullet App
$app = new Bullet\App();

$app->path('/', function($request) {
    return "Hello World!";
});

//$app = function($env) {
    //return array(200, array(), array("<pre>", print_r($env,true)));
//};

// Rack it up!
Rack::use_middleware("\Rackem\ShowExceptions");
Rack::run($app);

