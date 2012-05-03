<?php
require __DIR__ . '/src/Bullet/App.php';
$app = new Bullet\App();

//
// Handle: /blog/45/comments
// ----------------------------------------------------------
//
$app->path('blog', function($request) use($app) {
  echo "In Blog<br />\n";

  $app->param('intval', function($request, $id) use($app) {
    echo "Have blog id = $id<br />\n";

    $app->path('comments', function($request) use($app) {
      echo "In blog comments<br />\n";
    
    });

  });


  $app->path('categories', function($request) use($app) {
    echo "In blog categories<br />\n";

  });

});

//
// Handle: /events/5/ratings
// ----------------------------------------------------------
//

$app->path('events', function($request) use($app) {
  echo "In events<br />\n";

  // Filter that returns boolean "false" will not be executed (no match)
  $app->param('is_bool', function($request, $id) use($app) {
    echo "Should not ever match... ever";
  });


  $app->param('intval', function($request, $id) use($app) {
    echo "Have event id = $id<br />\n";

    $app->path('ratings', function($request) use($app) {
      echo "In event ratings<br />\n";

    });

  });


  $app->path('reviews', function($request) use($app) {
    echo "In event reviews<br />\n";

  });

});

//
// ----------------------------------------------------------
//

// Run request
echo $app->run("GET", "blog/45/comments");
echo "<hr />";
echo $app->run("GET", "events/5/ratings");
