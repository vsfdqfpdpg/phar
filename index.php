<?php

use Symfony\Component\Console\Application;

require_once __DIR__ . "/vendor/autoload.php";

$app = new Application( "PHP archive tool", '1.0' );
$app->add( new PharCommand() );
$app->run();