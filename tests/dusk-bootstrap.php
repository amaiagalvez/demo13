<?php

$autoload = __DIR__.'/../vendor/autoload.php';

if (! is_file($autoload)) {
    throw new RuntimeException('Run the browser suite from the Laravel application root.');
}

$loader = require $autoload;
$loader->addPsr4('CustomerProjects13\\Tests\\', __DIR__.'/../vendor/amaia/customer-projects13/tests/');
$loader->addPsr4('Customers13\\Tests\\', __DIR__.'/../vendor/amaia/customers13/tests/');
