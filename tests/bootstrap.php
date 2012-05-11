<?php
spl_autoload_register(function($className) {
    require dirname(__DIR__) . '/src/' . str_replace(array('\\', '_'), '/', $className) . '.php';
});

