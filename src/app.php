<?php

require_once __DIR__.'/../vendor/autoload.php';

use Trnsprt\Application;

$dockerIp = long2ip(ip2long(gethostbyname('host.docker.internal')) & 0xFFFFFF00 | 0x00000001);

$debug = isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === $dockerIp);
return new Application($debug);
