<?php
spl_autoload_register(function($className) {
    $file = dirname(__DIR__) . '/src/' . str_replace(array('\\', '_'), '/', $className) . '.php';
    require $file;
});

