<?php

namespace {

	use PhpSentinel\BugCatcher\Command\PingCollectorCommand;
	use PhpSentinel\BugCatcher\Entity\RecordLog;
	use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
	use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

	/**
	 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
	 */
	return static function (ContainerConfigurator $container): void {
		$container
			->parameters()
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
			])
		;
		$services = $container->services()
			->defaults()
			->autowire()
			->autoconfigure();
		$services
			->load('PhpSentinel\\BugCatcher\\', '../src/')
			->exclude('../src/{DependencyInjection,DataFixtures,Entity,Factory,Extension,BugCatcherBundle.php}');

		$services->set(PingCollectorCommand::class)->arg('$collectors',[]);
	};

}