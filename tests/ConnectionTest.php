<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use Trnsprt\Application;

class ConnectionTest extends TestCase
{
    public function testHome()
    {
        $apiResponse = json_encode([
            'from' => null,
            'to' => null,
            'stations' => ['from' => [], 'to' => []],
            'connections' => [],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $apiResponse)]);
        $app = new Application(false, new Client([
            'handler' => HandlerStack::create($mock),
        ]));
        $response = $app->handle(Request::create('/'));

        self::assertTrue($response->isOk());
        self::assertStringContainsString('Swiss Public Transport', $response->getContent());
    }
}
