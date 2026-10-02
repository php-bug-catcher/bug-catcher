<?php

namespace BugCatcher;

use BugCatcher\Api\Processor\PerfBucketBatchProcessor;
use BugCatcher\Command\PerfPurgeCommand;
use BugCatcher\Command\PerfRollupCommand;
use BugCatcher\Controller\Admin\NotifierCrudController;
use BugCatcher\Controller\Admin\NotifierEmailCrudController;
use BugCatcher\Controller\Admin\NotifierFaviconCrudController;
use BugCatcher\Controller\Admin\NotifierSoundCrudController;
use BugCatcher\Controller\Admin\ProjectCrudController;
use BugCatcher\Controller\Admin\UserCrudController;
use BugCatcher\Controller\DashboardController;
use BugCatcher\Controller\SecurityController;
use BugCatcher\Mcp\RecordTypes;
use BugCatcher\Repository\RecordLogTraceRepository;
use BugCatcher\Repository\RecordRepository;
use BugCatcher\Repository\RecordRepositoryInterface;
use BugCatcher\Enum\PerfMetric;
use BugCatcher\Security\McpAccessTokenHandler;
use BugCatcher\Service\Perf\Detection\Extractor\AvgMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\ErrorRateMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\MemMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\P95MetricExtractor;
use BugCatcher\Service\Perf\Detection\MetricExtractorRegistry;
use BugCatcher\Service\Perf\Retention\RetentionPolicy;
use BugCatcher\Service\Perf\Rollup\PathCapEnforcer;
use BugCatcher\Twig\Components\Favicon;
use BugCatcher\Twig\Components\LogList;
use BugCatcher\Twig\Components\StatusList;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
final class BugCatcherBundle extends AbstractBundle
{
	public function build(ContainerBuilder $container) {
		parent::build($container);
	}


	public function configure(DefinitionConfigurator $definition): void {
		$definition->import('../config/definition.php');
	}

	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
		$container->import('../config/services.php');
		$services = $container->services();

		$services->set(RecordLogTraceRepository::class)
			->autowire()
			->autoconfigure()
			->arg('$clearStackTrace', $config["clear_stacktrace_on_fixed"]);
		$services->set(PerfBucketBatchProcessor::class)
			->autowire()
			->autoconfigure()
			->arg('$enabled', $config["perf"]["enabled"]);
		$services->set(PathCapEnforcer::class)
			->autowire()
			->autoconfigure()
			->arg('$cap', $config["perf"]["rollup_path_cap"]);
		// the four built-ins, with bug_catcher.perf.metrics merged over them - the same named-map
		// idiom PingCollectorCommand uses, so a custom metric needs no tag and no compiler pass
		$metrics = [
			PerfMetric::P95->value       => service(P95MetricExtractor::class),
			PerfMetric::Avg->value       => service(AvgMetricExtractor::class),
			PerfMetric::ErrorRate->value => service(ErrorRateMetricExtractor::class),
			PerfMetric::Mem->value       => service(MemMetricExtractor::class),
		];
		foreach ($config["perf"]["metrics"] as $name => $id) {
			$metrics[$name] = service($id);
		}
		$services->set(MetricExtractorRegistry::class)
			->autowire()
			->autoconfigure()
			->arg('$extractors', $metrics);
		$services->set(RetentionPolicy::class)
			->autowire()
			->autoconfigure()
			->arg('$retention', $config["perf"]["retention"]);
		foreach ([PerfRollupCommand::class, PerfPurgeCommand::class] as $class) {
			$services->set($class)
				->autowire()
				->autoconfigure()
				->arg('$enabled', $config["perf"]["enabled"]);
		}
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
			->arg('$logo', $config["logo"]);
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
		$services->set(ProjectCrudController::class)
			->autowire()
			->public()
			->tag('controller.service_arguments')
			->tag('container.service_subscriber')
			->tag('ea.crud_controller')
			->arg('$collectors', $config["collectors"]);
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
			->arg('$classes', $config["dashboard_list_items"])
			->arg('$noBugFunnyMessages', $config["no_bug_funny_messages"]);
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

		$services->set(McpAccessTokenHandler::class)
			->autowire()
			->autoconfigure()
			->arg('$accessToken', $config["mcp"]["access_token"]);

		// RecordFinder and RecordTools pick this up by autowiring; it is the one MCP service that
		// needs the configuration handed to it
		$services->set(RecordTypes::class)
			->autowire()
			->autoconfigure()
			->arg('$classes', $config["mcp"]["record_types"]);

        $services->set(RecordRepositoryInterface::class)
            ->autowire()
            ->autoconfigure()
            ->class(RecordRepository::class)
            ->public(true);
	}


}