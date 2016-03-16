<?php

use Silex\WebTestCase;

class ConntextionTest extends WebTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../src/app.php';
        $app['debug'] = true;
        unset($app['exception_handler']);

        return $app;
    }

    public function testHome()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isOk());
        $this->assertContains('Swiss Public Transport', $crawler->filter('h1')->text());
    }
}
