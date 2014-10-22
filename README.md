Bullet
======

Bullet is a resource-oriented micro PHP framework built around HTTP URIs.
Bullet takes a unique functional-style approach to URL routing by parsing
each path part independently and one at a time using nested closures. The
path part callbacks are nested to produce different responses and to follow
and execute deeper paths as paths and parameters are matched.

[![Build
Status](https://secure.travis-ci.org/vlucas/bulletphp.png)](http://travis-ci.org/vlucas/bulletphp)

Requirements
------------

 * PHP 5.3+ (PHP 5.4 recommended)
 * [Composer](http://getcomposer.org) for all package management and
   autoloading (may require command-line access)

Rules
-----

 * Apps are **built around HTTP URIs** and defined paths, not forced MVC
   (but MVC-style separation of concerns is still highly recommenended and
   encouraged)
 * Bullet handles **one segment of the path at a time**, and executes the
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
 * If the path can be fully consumed, and HTTP method handlers are present
   in the path but none are matched, a 405 "Method Not Allowed" response
   will be returned.
 * If the path can be fully consumed, and format handlers are present in
   the path but none are matched, a 406 "Not Acceptable" response will
   be returned.

Advantages
----------

 * **Super flexible routing**. Because of the way the routing callbacks are
   nested, Bullet's routing system is one of the most flexible of any other PHP
   framework or library. You can build any URL you want and respond to any HTTP
   method on that URL. Routes are not restricted to specific patterns or URL
   formats, and do not require a controller with specific method names to
   respond to specific HTTP methods. You can nest routes as many levels deep as
   you want to expose nested resources like `posts/42/comments/943/edit` with a
   level of ease not found in most other routing libraries or frameworks.

 * **Reduced code duplication (DRY)**. Bullet takes full advantage of its nested
   closure routing system to reduce a lot of typical code duplication required
   in most other frameworks. In a typical MVC framework controller, some code
   has to be duplicated across methods that perform CRUD operations to run ACL
   checks and load required resources like a Post object to view, edit or delete.
   With Bullet's nested closure style, this code can be written just once in a
   path or param callback, and then you can `use` the loaded object in subsequent
   path, param, or HTTP method handlers. This eliminates the need for "before"
   hooks and filters, because you can just run the checks and load objects you
   need before you define other nested paths and `use` them when required.

Installing with Composer
-----
Use the [basic usage guide](http://getcomposer.org/doc/01-basic-usage.md),
or follow the steps below:

Setup your `composer.json` file at the root of your project

    {
        "require": {
            "vlucas/bulletphp": "*"
        }
    }


Install Composer

    curl -s http://getcomposer.org/installer | php


Install Dependencies (will download Bullet)

    php composer.phar install


Create `index.php` (use the minimal example below to get started)

    <?php
    require __DIR__ . '/vendor/autoload.php';

    // Your App
    $app = new Bullet\App();
    $app->path('/', function($request) {
        return "Hello World!";
    });

    // Run the app! (takes $method, $url or Bullet\Request object)
    echo $app->run(new Bullet\Request());


Use an `.htaccess` file for mod_rewrite (if you're using Apache)

    <IfModule mod_rewrite.c>
      RewriteEngine On

      # Reroute any incoming requestst that is not an existing directory or file
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteRule ^(.*)$ index.php?u=$1 [L,QSA,B]
    </IfModule>


View it in your browser!

Syntax
------

Bullet is not your typical PHP micro framework. Instead of defining a full
path pattern or a typical URL route with a callback and parameters mapped
to a REST method (GET, POST, etc.), Bullet parses only ONE URL segment
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


    $app = new Bullet\App(array(
        'template.cfg' => array('path' => __DIR__ . '/templates')
    ));

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
                return $app->template('posts/index', compact('posts'));
            });

            // Handle POST on this path
            $app->post(function() use($posts) {
                // Create new post
                $post = new Post($request->post());
                $mapper->save($post);
                return $this->response($post->toJSON(), 201);
            });

            // Handle DELETE on this path
            $app->delete(function() use($posts) {
                // Delete entire posts collection
                $posts->deleteAll();
                return 200;
            });

        });
    });

    // Run the app and echo the response
    echo $app->run("GET", "blog/posts");


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


    $app = new Bullet\App(array(
        'template.cfg' => array('path' => __DIR__ . '/templates')
    ));
    $app->path('posts', function($request) use($app) {
        // Integer path segment, like 'posts/42'
        $app->param('int', function($request, $id) use($app) {
            $app->get(function($request) use($id) {
                // View post
                return 'view_' . $id;
            });
            $app->put(function($request) use($id) {
                // Update resource
                $post->data($request->post());
                $post->save();
                return 'update_' . $id;
            });
            $app->delete(function($request) use($id) {
                // Delete resource
                $post->delete();
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
    echo $app->run('DELETE', '/posts/42'); // 'delete_42'

    echo $app->run('DELETE', '/posts/my-post-title'); // 'my-post-title'


Returning JSON (Useful for PHP JSON APIs)
-----------------------------------------

Bullet has built-in support for returning JSON responses. If you return
an array from a route handler (callback), Bullet will assume the
response is JSON and automatically `json_encode` the array and return the
HTTP response with the appropriate `Content-Type: application/json` header.


    $app->path('/', function($request) use($app) {
        $app->get(function($request) use($app) {
            // Links to available resources for the API
            $data = array(
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

            // Format responders
            $app->format('json', function($request), use($app, $data) {
                return $data; // Auto json_encode on arrays for JSON requests
            });
            $app->format('xml', function($request), use($app, $data) {
                return custom_function_convert_array_to_xml($data);
            });
            $app->format('html', function($request), use($app, $data) {
                return $app->template('index', array('links' => $data));
            });
        });
    });


### HTTP Response Bullet Sends:

    Content-Type:application/json

    {"_links":{"restaurants":{"title":"Restaurants","href":"http:\/\/yourdomain.local\/restaurants"},"events":{"title":"Events","href":"http:\/\/yourdomain.local\/events"}}}


Bullet Response Types
--------------

There are many possible values you can return from a route handler in
Bullet to produce a valid HTTP response. Most types can be either
returned directly, or wrapped in the `$app->response()` helper for
additional customization.

### Strings


    $app = new Bullet\App();
    $app->path('/', function($request) use($app) {
        return "Hello World";
    });
    $app->path('/', function($request) use($app) {
        return $app->response("Hello Error!", 500);
    });

Strings result in a 200 OK response with a body containing the returned
string. If you want to return a quick string response with a different
HTTP status code, use the `$app->response()` helper.

### Booleans


    $app = new Bullet\App();
    $app->path('/', function($request) use($app) {
        return true;
    });
    $app->path('notfound', function($request) use($app) {
        return false;
    });

Boolean `false` results in a 404 "Not Found" HTTP response, and boolean
`true` results in a 200 "OK" HTTP response.

### Integers


    $app = new Bullet\App();
    $app->path('teapot', function($request) use($app) {
        return 418;
    });

Integers are mapped to their corresponding HTTP status code. In this
example, a 418 "I'm a Teapot" HTTP response would be sent.

### Arrays

    $app = new Bullet\App();
    $app->path('foo', function($request) use($app) {
        return array('foo' => 'bar');
    });
    $app->path('bar', function($request) use($app) {
        return $app->response(array('bar' => 'baz'), 201);
    });

Arrays are automatically passed through `json_encode` and the appropriate
`Content-Type: application/json` HTTP response header is sent.

### Templates

    // Configure template path with constructor
    $app = new Bullet\App(array(
        'template.cfg' => array('path' => __DIR__ . '/templates')
    ));

    // Routes
    $app->path('foo', function($request) use($app) {
        return $app->template('foo');
    });
    $app->path('bar', function($request) use($app) {
        return $app->template('bar', array('bar' => 'baz'), 201);
    });

The `$app->template()` helper returns an instance of
`Bullet\View\Template` that is lazy-rendered on `__toString` when the
HTTP response is sent. The first argument is a template name, and the
second (optional) argument is an array of parameters to pass to the
template for use.


Nested Requests (HMVC style code re-use)
----------------------------------------

Since you explicitly `return` values from Bullet routes instead of
sending output directly, nested/sub requests are straightforward and easy.
All route handlers will return `Bullet\Response` instances (even if they
return a raw string or other data type, they are wrapped in a response
object by the `run` method), and they can be composed to form a single
HTTP response.

    $app = new Bullet\App();
    $app->path('foo', function($request) use($app) {
        return "foo";
    });
    $app->path('bar', function($request) use($app) {
        $foo = $app->run('GET', 'foo'); // $foo is now a `Bullet\Response` instance
        return $foo->content() . "bar";
    });
    echo $app->run('GET', 'bar'); // echos 'foobar' with a 200 OK status



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

