<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\VarExporter\VarExporter;

$yaml = Yaml::parse(file_get_contents('https://raw.githubusercontent.com/vasile/swiss-transit-colors/master/agency_colors.yml'), 0, 128, 1000);

$agencies = [];
foreach ($yaml['agency'] as $agency) {
    if (isset($agencies[$agency['short_name']])) {
        $agencies[$agency['short_name']] = array_merge($agencies[$agency['short_name']], $agency['vehicle_types']);
    } else {
        $agencies[$agency['short_name']] = $agency['vehicle_types'];
    }
}

$export = VarExporter::export($agencies);

file_put_contents(__DIR__ . '/src/agency-colors.php', "<?php\nreturn $export;\n");
