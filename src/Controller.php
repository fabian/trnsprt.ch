<?php

namespace Trnsprt;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Controller
{
    public function __construct(
        private Application $app,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
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
        $response = $this->app->fetch('connections', $query);

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

        return $this->app->render('connections.html.twig', [
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
        $response = $this->app->fetch('stationboard', $query);

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

        return $this->app->render('stationboard.html.twig', [
            'station' => $station,
            'datetime' => $datetime,
            'stationboard' => $stationboard,
            'coordinates' => $coordinates,
        ]);
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
        $color = $this->app->getAgencyColor($journey->operator, $journey->category, $journey->number);
        if ($color) {
            $journey->bg = $color['bg'];
            $journey->fg = $color['fg'];
        }
    }
}
