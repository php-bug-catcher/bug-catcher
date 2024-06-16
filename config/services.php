<?php

namespace {

	use PhpSentinel\BugCatcher\Command\PingCollectorCommand;
	use PhpSentinel\BugCatcher\Entity\RecordLog;
	use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
	use PhpSentinel\BugCatcher\Service\PingCollector\HttpPingCollector;
	use PhpSentinel\BugCatcher\Service\PingCollector\MessengerCollector;
	use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
	use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

	/**
	 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
	 */
	return static function (ContainerConfigurator $container): void {
		$container
			->parameters()
			->set("logo", "default")
			->set("refresh_interval", "15")
			->set("dashboard_components", [
				"StatusList",
				"LogList",
			])
			->set("dashboard_list_items", [
				RecordLog::class,
				RecordLogTrace::class
			])
			->set("status_list_components", [
				"ProjectStatus",
				"LogCount",
				"LogSparkLine",
				"WarningSound",
			])
			->set("detail_components", [
				RecordLogTrace::class => [
					'Detail:Header',
					'Detail:Title',
					'Detail:HistoryList',
					'Detail:StackTrace',
				],
				RecordLog::class      => [
					'Detail:Header',
					'Detail:Title',
					'Detail:HistoryList',
				],
			])
			->set("collectors", [
				'http',
				'messenger',
			])
		;
		$services = $container->services()
			->defaults()
			->autowire()
			->autoconfigure();
		$services
			->load('PhpSentinel\\BugCatcher\\', '../src/')
			->exclude('../src/{DependencyInjection,DataFixtures,Entity,Factory,Extension,BugCatcherBundle.php}');

		$services->set(PingCollectorCommand::class)->arg('$collectors',[
			'http'=>service(HttpPingCollector::class),
			'messenger'=>service(MessengerCollector::class),
		]);
	};

}