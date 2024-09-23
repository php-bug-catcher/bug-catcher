<?php

namespace {

	use BugCatcher\Command\PingCollectorCommand;
	use BugCatcher\Entity\RecordLog;
	use BugCatcher\Entity\RecordLogTrace;
	use BugCatcher\Repository\RecordLogTraceRepository;
    use BugCatcher\Repository\RecordRepositoryInterface;
    use BugCatcher\Service\PingCollector\HttpPingCollector;
	use BugCatcher\Service\PingCollector\MessengerCollector;
	use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
    use Tito10047\DoctrineTransaction\TransactionManager;
    use Tito10047\DoctrineTransaction\TransactionManagerInterface;
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
			->load('BugCatcher\\', '../src/')
			->exclude('../src/{DependencyInjection,DataFixtures,Entity,DTO,Event,Factory,Extension,BugCatcherBundle.php}');

		$services->set(PingCollectorCommand::class)->arg('$collectors',[
			'http'=>service(HttpPingCollector::class),
			'messenger'=>service(MessengerCollector::class),
		]);
        $services->set(TransactionManagerInterface::class)
            ->class(TransactionManager::class)
            ->arg('$mr', service('doctrine'));
	};

}