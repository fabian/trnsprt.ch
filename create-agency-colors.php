<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$yaml = Yaml::parse(file_get_contents('https://raw.githubusercontent.com/vasile/swiss-transit-colors/master/agency_colors.yml'));

$agencies = [];
foreach ($yaml['agency'] as $agency) {
    if (isset($agencies[$agency['short_name']])) {
        $agencies[$agency['short_name']] = array_merge($agencies[$agency['short_name']], $agency['vehicle_types']);
    } else {
        $agencies[$agency['short_name']] = $agency['vehicle_types'];
    }
}

$export = var_export($agencies, true);

file_put_contents(__DIR__ . '/src/agency-colors.php', "<?php\nreturn $export;\n");
