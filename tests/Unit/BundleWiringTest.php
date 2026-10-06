<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit;

use BugCatcher\BugCatcherBundle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Two names in `bug_catcher.perf` select a service by string, and both of them are typed by a
 * person into a YAML file. A typo in either is a configuration error, so it has to stop the
 * container being built - not surface at five in the morning as a stack trace out of cron, on the
 * first window the detector looks at.
 */
class BundleWiringTest extends TestCase
{
	public function testAMetricNobodyRegisteredStopsTheBuild(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/anomaly\.metric is db_time.*p95, avg/');

		$this->load(['anomaly' => ['metric' => 'db_time']]);
	}

	public function testADetectorNobodyRegisteredStopsTheBuild(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/detectors names slo.*regression/');

		$this->load(['detectors' => ['slo']]);
	}

	public function testTheShippedConfigurationBuilds(): void
	{
		$this->load([]);

		$this->addToAssertionCount(1);
	}

	/** A metric the application registered is a metric the detector may be pointed at. */
	public function testAnApplicationsOwnMetricMayBeSelected(): void
	{
		$this->load([
			'metrics' => ['db_time' => 'App\Perf\DbTimeExtractor'],
			'anomaly' => ['metric' => 'db_time'],
		]);

		$this->addToAssertionCount(1);
	}

	/**
	 * The perf panels live on `/performance`, and both lists are resolved by component name - so
	 * a leftover `dashboard_components` entry would quietly keep drawing them on the homepage.
	 */
	public function testAPerformancePanelLeftOnTheDashboardStopsTheBuild(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/dashboard_components lists PerfOverview.*performance_components/');

		$this->load([], ['dashboard_components' => ['StatusList', 'PerfOverview']]);
	}

	/**
	 * @param array<string, mixed> $perf overrides on top of the shipped defaults, merged one deep
	 * @param array<string, mixed> $root overrides on the root configuration
	 */
	private function load(array $perf, array $root = []): void
	{
		$defaults = [
			'enabled'           => true,
			'retention'         => ['minute' => '7 days', 'hour' => '90 days', 'day' => '2 years'],
			'rollup_path_cap'   => 1000,
			'anomaly'           => ['metric' => 'p95', 'factor' => 3.0, 'min_absolute_ms' => 200, 'min_hits' => 20],
			'baseline'          => ['lookback_weeks' => 4],
			'metrics'           => [],
			'detectors'         => ['regression'],
			'detector_services' => [],
		];

		foreach ($perf as $key => $value) {
			$defaults[$key] = is_array($value) && is_array($defaults[$key] ?? null)
				? [...$defaults[$key], ...$value]
				: $value;
		}

		$config = [
			'perf'                   => $defaults,
			'clear_stacktrace_on_fixed' => true,
			'detail_components'      => [],
			'dashboard_components'   => [],
			'performance_components' => ['PerfOverview', 'PerfTopPaths', 'PerfDatabase'],
			'refresh_interval'       => 15,
			'logo'                   => 'default',
			'app_name'               => 'BugCatcher',
			'roles'                  => [],
			'collectors'             => [],
			'notifier_components'    => [],
			'dashboard_list_items'   => [],
			'no_bug_funny_messages'  => [],
			'status_list_components' => [],
			'perf_status_list_components' => [],
			'worker_status_list_components' => [],
			'mcp'                    => ['access_token' => null, 'record_types' => []],
			...$root,
		];

		$path       = dirname(__DIR__, 2) . '/config';
		$container  = new ContainerBuilder();
		$instanceof = [];

		(new BugCatcherBundle())->loadExtension(
			$config,
			new ContainerConfigurator(
				$container,
				new PhpFileLoader($container, new FileLocator($path)),
				$instanceof,
				$path,
				'services.php',
			),
			$container,
		);
	}
}
