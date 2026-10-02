<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit;

use BugCatcher\BugCatcherBundle;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Entity\RecordPerformance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * What an application that writes nothing but `bug_catcher: ~` gets.
 *
 * The test application overrides most of these lists, because it has a record type of its own, so
 * nothing else in the suite ever sees the shipped defaults. They are a public contract: adding a
 * record type to `dashboard_list_items` changes what every dashboard shows on upgrade, and a
 * component name that does not exist is a broken detail page.
 */
class BundleDefaultsTest extends TestCase
{
	public function testARegressionIsListedOnTheDashboardAndHasADetailPage(): void
	{
		$config = $this->defaults();

		$this->assertSame(
			[RecordLog::class, RecordLogTrace::class, RecordPerformance::class],
			$config['dashboard_list_items'],
		);
		$this->assertSame(
			['Detail:Header', 'Detail:Title', 'Detail:HistoryList', 'Detail:PerfChart'],
			$config['detail_components'][RecordPerformance::class],
		);
	}

	/**
	 * Reading records is a decision of its own - an assistant holding the token can resolve what
	 * it finds - so a new record type does not join the MCP list by being added to the bundle.
	 */
	public function testTheNewRecordTypeIsNotReadableOverMcpUntilSomebodySaysSo(): void
	{
		$this->assertSame(
			[RecordLog::class, RecordLogTrace::class],
			$this->defaults()['mcp']['record_types'],
		);
	}

	public function testPerformanceMonitoringIsOnWithThresholdsThatStayQuiet(): void
	{
		$perf = $this->defaults()['perf'];

		$this->assertTrue($perf['enabled']);
		$this->assertSame(['minute' => '7 days', 'hour' => '90 days', 'day' => '2 years'], $perf['retention']);
		$this->assertSame(1000, $perf['rollup_path_cap']);
		$this->assertSame(
			['metric' => 'p95', 'factor' => 3.0, 'min_absolute_ms' => 200, 'min_hits' => 20],
			$perf['anomaly'],
		);
		$this->assertSame(['lookback_weeks' => 4], $perf['baseline']);
		$this->assertSame(['regression'], $perf['detectors']);
		$this->assertSame([], $perf['metrics']);
		$this->assertSame([], $perf['detector_services']);
	}

	/**
	 * The dashboard components are opt-in, as documented: a charting panel appearing on everyone's
	 * wall monitor on upgrade is not a default anybody chose.
	 */
	public function testThePerformancePanelsAreNotAddedToAnybodysDashboardOnUpgrade(): void
	{
		$config = $this->defaults();

		$this->assertSame(['StatusList', 'LogList'], $config['dashboard_components']);
		$this->assertNotContains('PerfSparkLine', $config['status_list_components']);
	}

	/** @dataProvider configurationThatMakesNoSense */
	public function testAValueThatCouldOnlyBeAMistakeIsRefused(array $config): void
	{
		$this->expectException(InvalidConfigurationException::class);

		$this->process([['perf' => $config]]);
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function configurationThatMakesNoSense(): array
	{
		return [
			// "report a regression when it gets faster"
			'a factor below one'     => [['anomaly' => ['factor' => 0.5]]],
			'no hits at all'         => [['anomaly' => ['min_hits' => 0]]],
			'a cap of no paths'      => [['rollup_path_cap' => 0]],
			'an empty metric name'   => [['anomaly' => ['metric' => '']]],
			'no weeks to look back'  => [['baseline' => ['lookback_weeks' => 0]]],
		];
	}

	/** @return array<string, mixed> */
	private function defaults(): array
	{
		return $this->process([]);
	}

	/**
	 * The same path `AbstractBundle` takes: the definition file is imported into a tree and the
	 * tree is processed.
	 *
	 * @param list<array<string, mixed>> $configs
	 * @return array<string, mixed>
	 */
	private function process(array $configs): array
	{
		$config      = dirname(__DIR__, 2) . '/config';
		$treeBuilder = new TreeBuilder('bug_catcher');

		(new BugCatcherBundle())->configure(new DefinitionConfigurator(
			$treeBuilder,
			new DefinitionFileLoader($treeBuilder, new FileLocator($config), new ContainerBuilder()),
			$config,
			'definition.php',
		));

		return (new Processor())->process($treeBuilder->buildTree(), $configs);
	}
}
