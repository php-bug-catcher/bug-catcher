<?php

namespace PhpSentinel\BugCatcher;

use PhpSentinel\BugCatcher\Controller\Admin\NotifierCrudController;
use PhpSentinel\BugCatcher\Controller\Admin\NotifierEmailCrudController;
use PhpSentinel\BugCatcher\Controller\Admin\NotifierFaviconCrudController;
use PhpSentinel\BugCatcher\Controller\Admin\NotifierSoundCrudController;
use PhpSentinel\BugCatcher\Controller\Admin\UserCrudController;
use PhpSentinel\BugCatcher\Controller\DashboardController;
use PhpSentinel\BugCatcher\Controller\SecurityController;
use PhpSentinel\BugCatcher\Repository\RecordLogTraceRepository;
use PhpSentinel\BugCatcher\Twig\Components\Favicon;
use PhpSentinel\BugCatcher\Twig\Components\LogList;
use PhpSentinel\BugCatcher\Twig\Components\StatusList;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
class BugCatcherBundle extends AbstractBundle {
	public function build(ContainerBuilder $container) {
		parent::build($container);
	}


	public function configure(DefinitionConfigurator $definition): void {
		$definition->import('../config/definition.php');
	}

	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
		$container->import('../config/services.php');
		$services = $container->services();

		$services->set(RecordLogTraceRepository::class)->arg('$clearStackTrace', $config["clear_stacktrace_on_fixed"]);
		$services->set(DashboardController::class)
			->autowire()
			->autoconfigure()
			->public()
			->arg('$classesComponents', $config["detail_components"])
			->arg('$components', $config["dashboard_components"])
			->arg('$refreshInterval', $config["refresh_interval"]);
		$services->set(SecurityController::class)
			->autowire()
			->public()
			->tag('controller.service_arguments')
			->tag('container.service_subscriber')
			->arg('$logo', $config["logo"])
			->arg('$appName', $config["app_name"]);
		$services->set(Controller\Admin\DashboardController::class)
			->autowire()
			->public()
			->tag('controller.service_arguments')
			->tag('container.service_subscriber')
			->arg('$appName', $config["app_name"]);
		$services->set(UserCrudController::class)
			->autowire()
			->public()
			->tag('controller.service_arguments')
			->tag('container.service_subscriber')
			->tag('ea.crud_controller')
			->arg('$roles', $config["roles"]);
		foreach ([
					 NotifierEmailCrudController::class,
					 NotifierFaviconCrudController::class,
					 NotifierSoundCrudController::class,
				 ] as $class) {
			$services->set($class)
				->autowire()
				->public()
				->tag('controller.service_arguments')
				->tag('container.service_subscriber')
				->tag('ea.crud_controller')
				->arg('$components', $config["notifier_components"]);
		}
		$services->set(LogList::class)
			->autowire()
			->autoconfigure()
			->arg('$classes', $config["dashboard_list_items"]);
		$services->set(LogList\RecordLog::class)
			->autowire()
			->autoconfigure()
			->arg('$classes', $config["dashboard_list_items"]);
		$services->set(StatusList::class)
			->autowire()
			->autoconfigure()
			->arg('$components', $config["status_list_components"]);
		$services->set(Favicon::class)
			->autowire()
			->autoconfigure()
			->arg('$logo', $config["logo"]);
	}


}