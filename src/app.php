<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;

use Igorw\Trashbin\Storage;
use Igorw\Trashbin\Validator;
use Igorw\Trashbin\Parser;

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

use Symfony\Component\Finder\Finder;


ErrorHandler::register();

$app = new Application();

$app['debug'] = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.options' => array('cache' => __DIR__.'/../cache/twig', 'debug' => true),
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['client'] = new GuzzleHttp\Client();

// enable the following URL variations:
// * /from/Basel/to/Zurich
// * /from/Basel/to/Zurich/tomorrow
// * /to/Basel/from/Zurich
// * /to/Basel/from/Zurich/tomorrow
// * /to/Zurich
// * /to/Zurich/tomorrow
$gotoConnections = function ($from, $to, $at, Request $request) use ($app) {
    return $app->handle(
        Request::create($app['url_generator']->generate(
            '_connections',
            array(
                'from' => $from,
                'to' => $to,
                'datetime' => $at,
                'c' =>  $request->query->get('c'),
                'page' => $request->query->get('page')
            )
        )),
        HttpKernelInterface::SUB_REQUEST
    );
};

$app->get('/', function (Request $request) use ($gotoConnections) {
    return $gotoConnections('', '', '', $request);
})
->bind('home');

$app->get('/to/{to}/from/{from}/at/{at}', function ($to = '', $from = '', $at = '', Request $request) use ($gotoConnections) {
    return $gotoConnections($from, $to, $at, $request);
})
->assert('to', '.+')
->assert('from', '.+')
->bind('connections');

$app->get('/to/{to}/from/{from}', function ($to, $from, Request $request) use ($gotoConnections) {
    return $gotoConnections($from, $to, '', $request);
})
->assert('to', '.+')
->assert('from', '.+');

$app->get('/from/{from}/to/{to}/at/{at}', function ($to, $from, $at, Request $request) use ($gotoConnections) {
    return $gotoConnections($from, $to, $at, $request);
})
->assert('to', '.+')
->assert('from', '.+');

$app->get('/from/{from}/to/{to}', function ($to, $from, Request $request) use ($gotoConnections) {
    return $gotoConnections($from, $to, '', $request);
})
->assert('to', '.+')
->assert('from', '.+');

$app->get('/to/{to}/at/{at}', function ($to, $at, Request $request) use ($gotoConnections) {
    return $gotoConnections('', $to, $at, $request);
})
->assert('to', '.+');

$app->get('/to/{to}', function ($to, Request $request) use ($gotoConnections) {
    return $gotoConnections('', $to, '', $request);
})
->assert('to', '.+');

$app->get('/from/{from}/at/{at}', function ($from, $at, Request $request) use ($gotoConnections) {
    return $gotoConnections($from, '', $at, $request);
})
->assert('from', '.+');

$app->get('/from/{from}', function ($from, Request $request) use ($gotoConnections) {
    return $gotoConnections($from, '', '', $request);
})
->assert('from', '.+');

$app->get('/c', function (Request $request) use ($app) {

    $query = $request->query->all();

    $url = 'http://transport.opendata.ch/v1/connections?' . http_build_query($query);
    $response = $app['client']->request('GET', $url);
    $response = json_decode($response->getBody());

    $from = $request->query->get('from');
    if ($response->from) {
        $from = $response->from->name;
    }

    $to = $request->query->get('to');
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

    $datetime = $request->query->get('datetime') ?: date('c');
    $page = $request->query->get('page') ?: 0;
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
->bind('_connections');

$app->get('/s', function (Request $request) use ($app) {

    $query = $request->query->all();

    $url = 'http://transport.opendata.ch/v1/stationboard?' . http_build_query($query);
    $response = json_decode($app['buzz']->get($url)->getContent());

    $station = $request->query->get('station');
    $coordinates = null;
    if ($response->station) {
        $station = $response->station->name;
        $coordinates = $response->station->coordinate;
    }

    $datetime = $request->query->get('datetime');
    $page = $request->query->get('page', 0);
    $c = $request->query->get('c');
    $stationboard = $response->stationboard;

    return $app['twig']->render('stationboard.html.twig', array(
        'station' => $station,
        'datetime' => $datetime,
        'stationboard' => $stationboard,
        'coordinates' => $coordinates,
    ));
})
->bind('stationboard');

return $app;
