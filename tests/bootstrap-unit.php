<?php

// Unit tests bootstrap: configure SQLite in-memory database
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

require_once __DIR__.'/../vendor/autoload.php';
