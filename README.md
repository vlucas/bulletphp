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

Installing with Composer
-----
Use the [basic usage guide](http://getcomposer.org/doc/01-basic-usage.md),
or follow the steps below:

Setup your `composer.json` file at the root of your project
```
{
    "require": {
        "vlucas/bulletphp": "*"
    }
}
```

Install Composer
```
curl -s http://getcomposer.org/installer | php
```

Install Dependencies (will download Bullet)
```
php composer.phar install
```

Create `index.php` (use the minimal example below to get started)
```
<?php
require __DIR__ . '/vendor/autoload.php';

// Your App
$app = new Bullet\App();
$app->path('/', function($request) {
    return "Hello World!";
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
```

Use an `.htaccess` file for mod_rewrite (if you're using Apache)
```
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Reroute any incoming requestst that is not an existing directory or file
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ index.php?u=$1 [L,QSA]
</IfModule>
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

### Capturing Path Parameters

Perhaps the most compelling use of URL routing is to capture path
segments and use them as parameters to fetch items from a database, like
`/posts/42` and `/posts/42/edit`. Bullet has a special `param` handler
for this that takes two arguments: a `test` callback that validates the
parameter type for use, and and a `Closure` callback. If the `test`
callback returns boolean `false`, the closure is never executed, and the
next path segment or param is tested. If it returns boolean `true`, the
captured parameter is passed to the Closure as the second argument.

Just like regular paths, HTTP method handlers can be nested inside param
callbacks, as well as other paths, more parameters, etc.

```
$app = new Bullet\App();
$app->path('posts', function($request) use($app) {
    // Digit
    $app->param('ctype_digit', function($request, $id) use($app) {
        $app->get(function($request) use($id) {
            // View resource
            return 'view_' . $id;
        });
        $app->put(function($request) use($id) {
            // Update resource
            return 'update_' . $id;
        });
        $app->delete(function($request) use($id) {
            // Delete resource
            return 'delete_' . $id;
        });
    });
    // All printable characters except space
    $app->param('ctype_graph', function($request, $slug) use($app) {
        return $slug; // 'my-post-title'
    });
});

// Results of above code
echo $app->run('GET',   '/posts/42'); // 'view_42'
echo $app->run('PUT',   '/posts/42'); // 'update_42'
echo $app->run('DELTE', '/posts/42'); // 'delete_42'

echo $app->run('DELTE', '/posts/my-post-title'); // 'my-post-title'
```

Returning JSON (Useful for PHP JSON APIs)
-----------------------------------------

Bullet has built-in support for returning JSON responses. If you return
an array from a route handler (callback), Bullet will assume the
response is JSON and automatically `json_encode` the array and return the
HTTP response with the appropriate `Content-Type: application/json` header.

```
$app->path('/', function($request) use($app) {
  $app->get(function($request) use($app) {
    // Links to available resources for the API
    return array(
      '_links' => array(
        'restaurants' => array(
          'title' => 'Restaurants',
          'href' => $app->url('restaurants')
        ),
        'events' => array(
          'title' => 'Events',
          'href' => $app->url('events')
        )
      )
    );
  });
});
```

### HTTP Response Bullet Sends:
```
Content-Type:application/json; charset=UTF-8

{"_links":{"restaurants":{"title":"Restaurants","href":"http:\/\/yourdomain.local\/restaurants"},"events":{"title":"Events","href":"http:\/\/yourdomain.local\/events"}}}
```

Running Tests
-------------

To run the Bullet test suite, simply run `phpunit` in the root of the
directory where the bullet files are in. Please make sure to add tests
and run the test suite before submitting pull requests for any contributions.

Credits
-------

Bullet - and specifically path-based callbacks that fully embrace HTTP
and encourage a more resource-oriented design - is something I have been
thinking about for a long time, and was finally moved to create it after
seeing [@joshbuddy](https://github.com/joshbuddy) give a presentation on [Renee](http://reneerb.com/)
(Ruby) at [Confoo](http://confoo.ca) 2012 in Montréal.

