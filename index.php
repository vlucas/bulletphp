<?php
use \Rackem\Rack;

require __DIR__ . '/src/Bullet/App.php';
require __DIR__ . '/vendor/autoload.php';

// Bullet App
$app = new Bullet\App();

$app->path('/', function($request) {
    return "Hello World!";
});

// Rack it up!
Rack::use_middleware("\Rackem\ShowExceptions");
Rack::run($app);

