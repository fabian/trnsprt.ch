<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Trnsprt\Application;

$request = Request::createFromGlobals();

$debug = IpUtils::checkIp($request->server->getString('REMOTE_ADDR'), [
    '127.0.0.1',
    '::1',
    gethostbyname('host.docker.internal').'/24',
]);

if ($debug) {
    Debug::enable();
}

$app = new Application($debug);
$app->handle($request)->send();
