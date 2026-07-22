<?php

use Symfony\Component\HttpFoundation\Request;

$app = require __DIR__.'/../src/app.php';
$app->handle(Request::createFromGlobals())->send();
