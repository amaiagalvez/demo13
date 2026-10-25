<?php

$autoload = __DIR__.'/../vendor/autoload.php';

if (! is_file($autoload)) {
    throw new RuntimeException('Run the browser suite from the Laravel application root.');
}

$loader = require $autoload;
$loader->addPsr4('Basics13\\Tests\\', __DIR__.'/../vendor/amaia/basics13/tests/');
$loader->addPsr4('EntityProjects13\\Tests\\', __DIR__.'/../vendor/amaia/entity-projects13/tests/');
$loader->addPsr4('Entities13\\Tests\\', __DIR__.'/../vendor/amaia/entities13/tests/');
$loader->addPsr4('Locations13\\Tests\\', __DIR__.'/../vendor/amaia/locations13/tests/');
$loader->addPsr4('Files13\\Tests\\', __DIR__.'/../vendor/amaia/files13/tests/');
$loader->addPsr4('Projects13\\Tests\\', __DIR__.'/../vendor/amaia/projects13/tests/');
