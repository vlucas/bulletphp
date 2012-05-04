Bullet
======

Bullet is an experimental resource-oriented micro PHP framework. Bullet
takes a unique approach by parsing each path part independently and one
at a time via callbacks. The path part callbacks are nested to produce
different responses and to follow and execute deeper paths.

Requirements
------------

 * PHP 5.3+ (heavy use of closures)
 * [Composer](http://getcomposer.org) for all package management and
   autoloading (may require command-line access)

Rules 
-----

 * Apps built around HTTP URIs, not forced MVC (but MVC-style separation
   of concerns is still highly recommenended and encouraged)
 * App handles one segment of the path at a time, and executes the
   callback for that path segment before proceesing to the next segment 
   (path callbacks are executed from left to right, until the entire path
   is consumed).
 * If the entire path cannot be consumed, a 404 error will be returned
   (note that some callbacks may have been executed before Bullet can
   know this due to the nature of callbacks and closures). Example: path
   `/events/45/edit` may return a 404 because there is no `edit` path
   callback, but paths `events` and `45` would have already been executed
   before Bullet can know to return a 404. This is why all your primary
   logic should be contained in `get`, `post`, or other method callbacks
   or in the model layer (and not in the bare `path` handlers).
 * If the path can be fully consumed, but the requested HTTP method
   callback is not present, a 405 "Method Not Allowed" response will be
   returned.

Setup
-----

Clone Bullet from git source
```
git clone git@github.com:vlucas/bullet.git
```

Install Composer
```
curl -s http://getcomposer.org/installer | php
```

Install Dependencies
```
php composer.phar install
```

Write code for your app (use the minimal example below to get started)
```
<?php
use \Rackem\Rack;

require __DIR__ . '/src/Bullet/App.php';
require __DIR__ . '/vendor/autoload.php';

// Your App
$app = new Bullet\App();
$app->path('/', function($request) {
    return "Hello World!";
});

// Rack it up!
Rack::use_middleware("\Rackem\ShowExceptions");
Rack::run($app);
```

View it in your browser!

Syntax
------

Bullet is not your typical PHP micro framework. Instead of defining a full
path pattern or a typical URL route with a callback and parameters mapped
to a REST method (GET, POST, etc.), Bullet parses only ONE URL segement
at a time, and only has two methods for working with paths: `path` and
`param`. As you may have guessed, `path` is for static path names like
"blog" or "events" that won't change, and `param` is for variable path
segments that need to be captured and used, like "42" or "my-post-title".
You can then respond to paths using nested HTTP method callbacks that
contain all the logic for the action you want to perform.

This type of unique callback nesting eliminates repetitive code for
loading records, checking authentication, and performing other setup
work found in typical MVC frameworks or other microframeworks where each
callback or action is in a separate scope or controller method.

```
$app = new Bullet\App();

// 'blog' subdirectory
$app->path('blog', function($request) use($app) {
    
    $blog = somehowGetBlogMapper(); // Your ORM or other methods here

    // 'posts' subdirectory in 'blog' ('blog/posts')
    $app->path('posts', function() use($app, $blog) {

        // Load posts once for handling by GET/POST/DELETE below
        $posts = $blog->allPosts(); // Your ORM or other methods here

        // Handle GET on this path
        $app->get(function() use($posts) {
            // Display all $posts
            return "GET";
        });

        // Handle POST on this path
        $app->post(function() use($posts) {
            // Create new post in collection...
            return "POST";
        });

        // Handle DELETE on this path
        $app->delete(function() use($posts) {
            // Delete entire posts collection
            return "DELETE";
        });

    });
});

// Run the app and echo the response
echo $app->run("GET", "blog/posts");
```

Deploying to Heroku
-------------------

Install the Heroku gem:
```
gem install heroku
```  

Create your Heroku app (cedar stack):
```
heroku create <appname> --stack cedar
```

Run this command (Only ONCE):
```
heroku config:add LD_LIBRARY_PATH=/app/php/ext:/app/apache/lib
```

Start a bash shell on your Heroku app server:
```
heroku run bash
```

Run the commands to download [Composer](http://getcomposer.org) and run the installer
```
cd www
curl -s http://getcomposer.org/installer | ~/php/bin/php
~/php/bin/php composer.phar install
```

Open your app (you may have to re-push to deploy again)
```
heroku open
```


Credits
-------

Bullet leverages [Rackem](https://github.com/tamagokun/rackem) (PHP
implementation of Ruby's Rack) to make using middleware a breeze.

