<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Trnsprt\Application;

class ApplicationTest extends TestCase
{
    public static function routes(): array
    {
        return [
            'home' => ['/', 200, 'name="from"'],
            'connections' => ['/from/Zürich/to/Bern', 200, 'name="from"'],
            'destination' => ['/to/Bern', 200, 'name="from"'],
            'origin' => ['/from/Zürich', 200, 'name="from"'],
            'connections API' => ['/c?from=Zürich&to=Bern', 200, 'name="from"'],
            'stationboard' => ['/s?station=Zürich', 200, 'name="station"'],
            'not found' => ['/not-found', 404, '404 Not Found'],
        ];
    }

    #[DataProvider('routes')]
    public function testRoutes(string $path, int $status, string $content): void
    {
        $response = $this->application()->handle(Request::create($path));

        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString($content, $response->getContent());
    }

    public function testConnectionRouteParameters(): void
    {
        $mock = new MockHandler([$this->apiResponse()]);
        $app = new Application(false, new Client(['handler' => HandlerStack::create($mock)]));

        $app->handle(Request::create('/from/Zürich/to/Bern/at/08:00'));

        parse_str($mock->getLastRequest()?->getUri()->getQuery(), $query);
        self::assertSame([
            'from' => 'Zürich',
            'to' => 'Bern',
            'datetime' => '08:00',
        ], $query);
    }

    public function testConnectionQueryParameters(): void
    {
        $mock = new MockHandler([$this->apiResponse()]);
        $app = new Application(false, new Client(['handler' => HandlerStack::create($mock)]));

        $app->handle(Request::create('/c?from=Zürich&to=Bern&page=1'));

        parse_str($mock->getLastRequest()?->getUri()->getQuery(), $query);
        self::assertSame([
            'from' => 'Zürich',
            'to' => 'Bern',
            'page' => '1',
            'datetime' => '',
        ], $query);
    }

    public function testRouteParametersTakePrecedenceOverQuery(): void
    {
        $mock = new MockHandler([$this->apiResponse()]);
        $app = new Application(false, new Client(['handler' => HandlerStack::create($mock)]));

        $app->handle(Request::create('/from/Basel/to/Genf?from=Zürich&to=Bern'));

        parse_str($mock->getLastRequest()?->getUri()->getQuery(), $query);
        self::assertSame([
            'from' => 'Basel',
            'to' => 'Genf',
            'datetime' => '',
        ], $query);
    }

    public function testInvalidQueryParameterNotFound(): void
    {
        $response = $this->application()->handle(Request::create('/?page=invalid'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('404 Not Found', $response->getContent());
    }

    public function testMalformedQueryParameterBadRequest(): void
    {
        $response = $this->application()->handle(Request::create('/?from[]=1'));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('An error occured', $response->getContent());
    }

    public function testApiErrorPage(): void
    {
        $response = $this->application(new Response(500, [], 'Unavailable'))->handle(Request::create('/'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('There was an error connecting to the Transport API', $response->getContent());
    }

    private function application(?Response $response = null): Application
    {
        $mock = new MockHandler([$response ?? $this->apiResponse()]);

        return new Application(false, new Client([
            'handler' => HandlerStack::create($mock),
        ]));
    }

    private function apiResponse(): Response
    {
        $apiResponse = json_encode([
            'from' => null,
            'to' => null,
            'stations' => ['from' => [], 'to' => []],
            'connections' => [],
            'station' => null,
            'stationboard' => [],
        ], JSON_THROW_ON_ERROR);

        return new Response(200, [], $apiResponse);
    }
}
