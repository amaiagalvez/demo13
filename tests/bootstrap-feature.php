<?php

// Feature tests bootstrap: configure MySQL database
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = 'db';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'laravel_phpunit';
$_ENV['DB_USERNAME'] = 'laravel';
$_ENV['DB_PASSWORD'] = 'laravel';

$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_HOST'] = 'db';
$_SERVER['DB_PORT'] = '3306';
$_SERVER['DB_DATABASE'] = 'laravel_phpunit';
$_SERVER['DB_USERNAME'] = 'laravel';
$_SERVER['DB_PASSWORD'] = 'laravel';

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=db');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=laravel_phpunit');
putenv('DB_USERNAME=laravel');
putenv('DB_PASSWORD=laravel');

require_once __DIR__.'/../vendor/autoload.php';
