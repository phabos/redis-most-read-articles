<?php

use Symfony\Component\HttpFoundation\Request;
use Silex\Application;
use Redis\RedisServiceProvider;

require_once __DIR__.'/vendor/autoload.php';

$app = new Application();

$app['ttl'] = 10; //in second
$app['redis-most-read-key'] = 'most-read-article';

$app->register( new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app['predis'] = function () use ( $app ) {
  return new \Predis\Client();
};

// Return array of best articles
$app['most-read-article'] = function () use ( $app ) {
    return $app['predis']->zrevrangebyscore( $app['redis-most-read-key'], '+inf', 0,
                                             [ 'limit' => [ '0', '10' ],
                                               'withscores' => true ]
                                           );
};

$app->before( function ( Request $request, Application $app ) {
    $ttl = $app['predis']->ttl( $app['redis-most-read-key'] );
    if( $ttl < 0 ) {
        $app['predis']->expire( $app['redis-most-read-key'], $app['ttl'] );
    }
    // Get your article & make json from array of data you wanna display
    $app['predis']->zincrby( $app['redis-most-read-key'], 1, $app->escape( $request->getUri() ) );
});

$app->match( '/{url}', function( $url ) use( $app ) {
    $actualRoute = $app->escape( $url );
    $bestArticles = $app['most-read-article'];
    return $app['twig']->render('index.twig', array(
        'actualRoute'  => $actualRoute,
        'bestArticles' => $bestArticles,
        'ttl'          => $app['ttl'] . ' seconds'
    ));
})
->method( 'GET|POST' )
->assert( 'url', '.+' )
->value( 'url', '' );

$app->run();
