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

Credits
-------

Bullet leverages [Rackem](https://github.com/tamagokun/rackem) (PHP
implementation of Ruby's Rack) to make using middleware a breeze.

