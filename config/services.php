<?php

namespace {

	use PhpSentinel\BugCatcher\Command\PingCollectorCommand;
	use PhpSentinel\BugCatcher\Entity\RecordLog;
	use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
	use PhpSentinel\BugCatcher\Repository\RecordLogTraceRepository;
	use PhpSentinel\BugCatcher\Service\PingCollector\HttpPingCollector;
	use PhpSentinel\BugCatcher\Service\PingCollector\MessengerCollector;
	use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
	use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

	/**
	 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
	 */
	return static function (ContainerConfigurator $container): void {

		$services = $container->services()
			->defaults()
			->autowire()
			->autoconfigure();
		$services
			->load('PhpSentinel\\BugCatcher\\', '../src/')
			->exclude('../src/{DependencyInjection,DataFixtures,Entity,DTO,Event,Factory,Extension,BugCatcherBundle.php}');

		$services->set(PingCollectorCommand::class)->arg('$collectors',[
			'http'=>service(HttpPingCollector::class),
			'messenger'=>service(MessengerCollector::class),
		]);
	};

}