<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Trnsprt\Application;

$request = Request::createFromGlobals();

$dockerIp = long2ip(ip2long(gethostbyname('host.docker.internal')) & 0xFFFFFF00 | 0x00000001);
$remoteAddress = $request->server->get('REMOTE_ADDR');
$debug = $remoteAddress === '127.0.0.1' || $remoteAddress === $dockerIp;

$app = new Application($debug);
$app->handle($request)->send();
