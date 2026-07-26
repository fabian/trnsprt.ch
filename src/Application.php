<?php

namespace Trnsprt;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Application implements HttpKernelInterface
{
    private array $agencyColors;
    private ClientInterface $client;
    private bool $debug;
    private UrlGenerator $urlGenerator;
    private UrlMatcher $urlMatcher;
    private RequestContext $requestContext;
    private Environment $twig;

    public function __construct(bool $debug = false, ?ClientInterface $client = null)
    {
        $this->agencyColors = require __DIR__.'/agency-colors.php';
        $this->client = $client ?? new Client();
        $this->debug = $debug;

        $routesLoader = new AttributeRouteLoader();
        $routes = $routesLoader->load(self::class);

        $this->requestContext = new RequestContext();
        $this->urlGenerator = new UrlGenerator($routes, $this->requestContext);
        $this->urlMatcher = new UrlMatcher($routes, $this->requestContext);

        $this->twig = new Environment(new FilesystemLoader(__DIR__.'/../views'), [
            'cache' => __DIR__.'/../cache/twig',
            'debug' => $this->debug,
        ]);
        $this->twig->addFunction(new TwigFunction(
            'path',
            fn (string $name, array $parameters = []): string => $this->urlGenerator->generate($name, $parameters)
        ));

        if ($this->debug) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        try {
            $this->requestContext->fromRequest($request);
            $route = $this->urlMatcher->match($request->getPathInfo());

            $controller = $route['_controller'];
            return $this->{$controller}($request, $route);
        } catch (ResourceNotFoundException) {
            return $this->render('404.html.twig', [], Response::HTTP_NOT_FOUND);
        } catch (ServerException $exception) {
            if ($this->debug || !$catch) {
                throw $exception;
            }

            return $this->render('error_api.html.twig', [
                'exception' => $exception,
                'response' => $exception->getResponse(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $exception) {
            if ($this->debug || !$catch) {
                throw $exception;
            }

            return $this->render('error.html.twig', [
                'exception' => $exception,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/', name: 'home', defaults: ['from' => '', 'to' => '', 'at' => ''], methods: ['GET'])]
    #[Route('/to/{to}/from/{from}/at/{at}', name: 'connections', methods: ['GET'])]
    #[Route('/to/{to}/from/{from}', name: 'connections_to_from', defaults: ['at' => ''], methods: ['GET'])]
    #[Route('/from/{from}/to/{to}/at/{at}', name: 'connections_from_to_at', methods: ['GET'])]
    #[Route('/from/{from}/to/{to}', name: 'connections_from_to', defaults: ['at' => ''], methods: ['GET'])]
    #[Route('/to/{to}/at/{at}', name: 'connections_to_at', defaults: ['from' => ''], methods: ['GET'])]
    #[Route('/to/{to}', name: 'connections_to', defaults: ['from' => '', 'at' => ''], methods: ['GET'])]
    #[Route('/from/{from}/at/{at}', name: 'connections_from_at', defaults: ['to' => ''], methods: ['GET'])]
    #[Route('/from/{from}', name: 'connections_from', defaults: ['to' => '', 'at' => ''], methods: ['GET'])]
    #[Route('/c', name: 'connections_api', methods: ['GET'])]
    public function connections(Request $request, array $route): Response
    {
        $query = array_merge($request->query->all(), [
            'from' => $route['from'] ?? '',
            'to' => $route['to'] ?? '',
            'datetime' => $route['at'] ?? '',
        ]);
        $response = $this->fetch('connections', $query);

        $from = $query['from'] ?? null;
        if ($response->from) {
            $from = $response->from->name;
        }

        $to = $query['to'] ?? null;
        if ($response->to) {
            $to = $response->to->name;
        }

        $stationsFrom = $this->stationSuggestions($response->stations->from);
        $stationsTo = $this->stationSuggestions($response->stations->to);

        $datetime = $query['datetime'] ?? null;
        $page = $query['page'] ?? 0;
        $c = $query['c'] ?? null;
        $isArrivalTime = $query['isArrivalTime'] ?? null;
        $connections = $response->connections;

        if ($connections) {
            if (!$datetime) {
                $datetime = date('Y-m-d H:i');
            }

            foreach ($connections as $connection) {
                foreach ($connection->sections as $section) {
                    if (is_object($section->journey)) {
                        $this->addJourneyColor($section->journey);
                    }
                }
            }
        }

        return $this->render('connections.html.twig', [
            'from' => $from,
            'to' => $to,
            'datetime' => $datetime,
            'page' => $page,
            'c' => $c,
            'isArrivalTime' => $isArrivalTime,
            'stationsFrom' => $stationsFrom,
            'stationsTo' => $stationsTo,
            'connections' => $connections,
        ]);
    }

    #[Route('/s', name: 'stationboard', methods: ['GET'])]
    public function stationboard(Request $request, array $route): Response
    {
        $query = $request->query->all();
        $response = $this->fetch('stationboard', $query);

        $station = $query['station'] ?? null;
        $coordinates = null;
        if ($response->station) {
            $station = $response->station->name;
            $coordinates = $response->station->coordinate;
        }

        $datetime = $query['datetime'] ?? null;
        $stationboard = $response->stationboard;

        foreach ($stationboard as $journey) {
            $this->addJourneyColor($journey);
        }

        return $this->render('stationboard.html.twig', [
            'station' => $station,
            'datetime' => $datetime,
            'stationboard' => $stationboard,
            'coordinates' => $coordinates,
        ]);
    }

    private function getAgencyColor(mixed $operator, mixed $category, mixed $number): ?array
    {
        if (isset($this->agencyColors[$operator][$category][$number]['bg'])) {
            return $this->agencyColors[$operator][$category][$number];
        }

        return $this->agencyColors[$operator][$category]['ALL_LINES'] ?? null;
    }

    private function fetch(string $endpoint, array $query): object
    {
        $url = "https://transport.opendata.ch/v1/$endpoint?".http_build_query($query);

        return json_decode((string) $this->client->request('GET', $url)->getBody());
    }

    private function stationSuggestions(array $stations): array
    {
        if (!isset($stations[0]) || $stations[0]->score >= 101) {
            return [];
        }

        $suggestions = [];
        foreach (array_slice($stations, 1, 3) as $station) {
            if ($station->score > 97) {
                $suggestions[] = $station->name;
            }
        }

        return $suggestions;
    }

    private function addJourneyColor(object $journey): void
    {
        $color = $this->getAgencyColor($journey->operator, $journey->category, $journey->number);
        if ($color) {
            $journey->bg = $color['bg'];
            $journey->fg = $color['fg'];
        }
    }

    private function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context), $status);
    }
}
