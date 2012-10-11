<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Igorw\Trashbin\Storage;
use Igorw\Trashbin\Validator;
use Igorw\Trashbin\Parser;

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

use Symfony\Component\Finder\Finder;


$app = new Application();

$app['debug'] = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.options' => array('cache' => __DIR__.'/../cache/twig', 'debug' => true),
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['buzz'] = new Buzz\Browser(new Buzz\Client\Curl());

$app->get('/', function () use ($app) {
    return $app->redirect($app['url_generator']->generate('connections'));
});

$app->get('/c', function (Request $request) use ($app) {

    $query = $request->query->all();

    $url = 'http://transport.opendata.ch/v1/connections?' . http_build_query($query);
    $response = json_decode($app['buzz']->get($url)->getContent());

    $from = $request->query->get('from');
    if ($response->from) {
        $from = $response->from->name;
    }

    $to = $request->query->get('from');
    if ($response->to) {
        $to = $response->to->name;
    }
    
    $stationsFrom = array();
    if (isset($response->stations->from[0])) {
        if ($response->stations->from[0]->score < 101) {
            foreach (array_slice($response->stations->from, 1, 3) as $station) {
                if ($station->score > 97) {
                    $stationsFrom[] = $station->name;
                }
            }
        }
    }

    $stationsTo = array();
    if (isset($response->stations->to[0])) {
        if ($response->stations->to[0]->score < 101) {
            foreach (array_slice($response->stations->to, 1, 3) as $station) {
                if ($station->score > 97) {
                    $stationsTo[] = $station->name;
                }
            }
        }
    }

    $datetime = $request->query->get('datetime');
    $page = $request->query->get('page', 0);
    $c = $request->query->get('c');
    $connections = $response->connections;

    return $app['twig']->render('connections.html.twig', array(
        'from' => $from,
        'to' => $to,
        'datetime' => $datetime,
        'page' => $page,
        'c' => $c,
        'stationsFrom' => $stationsFrom,
        'stationsTo' => $stationsTo,
        'connections' => $connections,
    ));
})
->bind('connections');

return $app;
