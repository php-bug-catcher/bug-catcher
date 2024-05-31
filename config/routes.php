<?php

use PhpSentinel\BugCatcher\Controller\HelloController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#routing
 */
return static function (RoutingConfigurator $routes): void {
    $routes
        ->add('php_hello_controller', '/')
            ->controller(HelloController::class)
            ->methods(['GET'])
    ;
};
