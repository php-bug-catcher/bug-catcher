<?php

namespace {

	use PhpSentinel\BugCatcher\Command\PingCollectorCommand;
	use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

	/**
	 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
	 */
	return static function (ContainerConfigurator $container): void {
		$container
			->parameters()// ->set('php.param_name', 'param_value');
		;
		$services = $container->services()
			->defaults()
			->autowire()
			->autoconfigure();
		$services
			->load('PhpSentinel\\BugCatcher\\', '../src/')
			->exclude('../src/{DependencyInjection,DataFixtures,Entity,Factory,BugCatcherBundle.php}');

		$services->set(PingCollectorCommand::class)->arg('$collectors',[]);
	};

}