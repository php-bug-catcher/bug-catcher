<?php

namespace BugCatcher;

use BugCatcher\Api\Processor\PerfBucketBatchProcessor;
use BugCatcher\Command\PerfDetectCommand;
use BugCatcher\Command\PerfPurgeCommand;
use BugCatcher\Command\PerfRollupCommand;
use BugCatcher\Controller\Admin\NotifierCrudController;
use BugCatcher\Controller\Admin\NotifierEmailCrudController;
use BugCatcher\Controller\Admin\NotifierFaviconCrudController;
use BugCatcher\Controller\Admin\NotifierSoundCrudController;
use BugCatcher\Controller\Admin\ProjectCrudController;
use BugCatcher\Controller\Admin\UserCrudController;
use BugCatcher\Controller\DashboardController;
use BugCatcher\Controller\PerformanceController;
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
use BugCatcher\Service\Perf\Detection\ConjunctiveThresholdPolicy;
use BugCatcher\Service\Perf\Detection\DayOfWeekBaselineProvider;
use BugCatcher\Service\Perf\Detection\DetectionRunner;
use BugCatcher\Service\Perf\Detection\MetricExtractorRegistry;
use BugCatcher\Service\Perf\Detection\RegressionDetector;
use BugCatcher\Service\Perf\Retention\RetentionPolicy;
use BugCatcher\Service\Perf\Rollup\PathCapEnforcer;
use BugCatcher\Twig\Components\Favicon;
use BugCatcher\Twig\Components\LogList;
use BugCatcher\Twig\Components\StatusList;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use InvalidArgumentException;
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

		// the detector would otherwise discover this at five in the morning, as a stack trace
		// out of cron, on the first window it looks at
		if (!isset($metrics[$config["perf"]["anomaly"]["metric"]])) {
			throw new InvalidArgumentException(sprintf(
				'bug_catcher.perf.anomaly.metric is %s, which is not a metric. Known: %s.',
				$config["perf"]["anomaly"]["metric"],
				implode(', ', array_keys($metrics)),
			));
		}
		$services->set(RetentionPolicy::class)
			->autowire()
			->autoconfigure()
			->arg('$retention', $config["perf"]["retention"]);
		$services->set(ConjunctiveThresholdPolicy::class)
			->autowire()
			->autoconfigure()
			->arg('$factor', $config["perf"]["anomaly"]["factor"])
			->arg('$minAbsoluteMs', $config["perf"]["anomaly"]["min_absolute_ms"])
			->arg('$minHits', $config["perf"]["anomaly"]["min_hits"]);
		$services->set(DayOfWeekBaselineProvider::class)
			->autowire()
			->autoconfigure()
			->arg('$lookbackWeeks', $config["perf"]["baseline"]["lookback_weeks"]);
		$services->set(RegressionDetector::class)
			->autowire()
			->autoconfigure()
			->arg('$metricName', $config["perf"]["anomaly"]["metric"]);

		// the built-in detector, with bug_catcher.perf.detector_services merged over it; an
		// unknown name in perf.detectors stops the build rather than quietly detecting nothing
		$detectors = [RegressionDetector::NAME => service(RegressionDetector::class)];
		foreach ($config["perf"]["detector_services"] as $name => $id) {
			$detectors[$name] = service($id);
		}
		$enabledDetectors = [];
		foreach ($config["perf"]["detectors"] as $name) {
			$enabledDetectors[] = $detectors[$name] ?? throw new InvalidArgumentException(sprintf(
				'bug_catcher.perf.detectors names %s, which is not a detector. Known: %s.',
				$name,
				implode(', ', array_keys($detectors)),
			));
		}
		$services->set(DetectionRunner::class)
			->autowire()
			->autoconfigure()
			->arg('$detectors', $enabledDetectors);

		foreach ([PerfRollupCommand::class, PerfPurgeCommand::class, PerfDetectCommand::class] as $class) {
			$services->set($class)
				->autowire()
				->autoconfigure()
				->arg('$enabled', $config["perf"]["enabled"]);
		}
		// the perf panels moved to /performance, and a stale dashboard_components entry would
		// otherwise keep rendering them on the homepage - silently, because a Twig component is
		// resolved by name and both names exist. Say so at build time instead.
		$misplaced = array_intersect($config["dashboard_components"], PerformanceController::PANELS);
		if ($misplaced !== []) {
			throw new InvalidArgumentException(sprintf(
				'bug_catcher.dashboard_components lists %s, which belong on the performance page. '
				. 'Move them to bug_catcher.performance_components, or drop them to take the defaults.',
				implode(', ', $misplaced),
			));
		}
		$services->set(DashboardController::class)
			->autowire()
			->autoconfigure()
			->public()
			->arg('$classesComponents', $config["detail_components"])
			->arg('$components', $config["dashboard_components"])
			->arg('$refreshInterval', $config["refresh_interval"]);
		$services->set(PerformanceController::class)
			->autowire()
			->autoconfigure()
			->public()
			->arg('$components', $config["performance_components"])
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
			->arg('$components', $config["status_list_components"])
			->arg('$perfComponents', $config["perf_status_list_components"])
			->arg('$perfEnabled', $config["perf"]["enabled"]);
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