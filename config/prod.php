<?php

// configure your app for the production environment
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$app['twig.path'] = array(__DIR__.'/../templates');
$app['twig.options'] = array('cache' => __DIR__.'/../var/cache/twig');
$app['VERIFY_TOKEN'] = 'aValidToken';
$app['PAGE_ACCESS_TOKEN'] = '';

$app['fblog'] = function() {
    return new Logger('fblog');
};
$app['fblog']->pushHandler(new StreamHandler(__DIR__.'/../var/logs/messenger.log', Logger::INFO));