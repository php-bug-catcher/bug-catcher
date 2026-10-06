<?php

use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Entity\RecordPerformance;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#configuration
 */
return static function (DefinitionConfigurator $definition): void {
    $definition
        ->rootNode()
            ->children()
		->scalarNode("logo")->defaultValue("default")->end()
		->integerNode("refresh_interval")->defaultValue(15)->end()
		->scalarNode("app_name")->defaultValue("BugCatcher")->end()
		->booleanNode("clear_stacktrace_on_fixed")->defaultValue(true)->end()
		->arrayNode("mcp")
		->addDefaultsIfNotSet()
		->children()
		// resolves to null when MCP_ACCESS_TOKEN is not set, and a null token refuses every
		// request - see McpAccessTokenHandler
		->scalarNode("access_token")->defaultValue('%env(default::MCP_ACCESS_TOKEN)%')->end()
		// the record types search_records, get_record_detail and set_record_status work on.
		// Subclasses of a listed class are included, so RecordLog brings RecordLogTrace. Kept
		// apart from dashboard_list_items: an AI holding the token can resolve what it finds, so
		// that is a decision to take, not a side effect of putting a type on a page. RecordPing
		// cannot be listed at all - it has neither a hash nor a component name. An empty list
		// would compile to "discr IN ()", hence the guards.
		->arrayNode("record_types")
		->defaultValue([
			RecordLog::class,
			RecordLogTrace::class,
		])
		->requiresAtLeastOneElement()
		->prototype('scalar')->cannotBeEmpty()->end()
		->end()
		->end()
		->end()
		// performance monitoring - see docs/performance.md. The collector that fills perf_bucket
		// is a package of its own (php-bug-catcher/perf-collector), installed on the monitored
		// machine; nothing here runs unless something ships buckets.
		->arrayNode("perf")
		->addDefaultsIfNotSet()
		->children()
		// false turns the ingest endpoint off with a 503, so a collector keeps its samples
		// instead of dropping them, and makes the commands and components stand down
		->booleanNode("enabled")->defaultTrue()->end()
		// how long each granularity is kept, as anything DateTimeImmutable understands.
		// app:perf:purge deletes beyond it; minutes roll up into hours and hours into days
		// exactly, so dropping them loses nothing a long-range chart would have shown
		->arrayNode("retention")
		->addDefaultsIfNotSet()
		->children()
		->scalarNode("minute")->defaultValue('7 days')->end()
		->scalarNode("hour")->defaultValue('90 days')->end()
		->scalarNode("day")->defaultValue('2 years')->end()
		->end()
		->end()
		// beyond this many distinct paths in one bucket the tail folds into '__other__'. It is a
		// backstop for a pattern PathNormalizer failed to cover, and the log line it writes is
		// what tells you to go fix the rule
		->integerNode("rollup_path_cap")->defaultValue(1000)->min(1)->end()
		// when a window counts as a regression. Deliberately conjunctive: the metric must rise
		// by at least factor AND exceed min_absolute_ms, so 5 ms becoming 20 ms stays quiet,
		// and the window needs min_hits before it counts at all
		->arrayNode("anomaly")
		->addDefaultsIfNotSet()
		->children()
		->scalarNode("metric")->defaultValue('p95')->cannotBeEmpty()->end()
		->floatNode("factor")->defaultValue(3.0)->min(1.0)->end()
		->integerNode("min_absolute_ms")->defaultValue(200)->min(0)->end()
		->integerNode("min_hits")->defaultValue(20)->min(1)->end()
		->end()
		->end()
		// the baseline is the same time of day on previous days, matched on day of week -
		// Monday morning is not Sunday night
		->arrayNode("baseline")
		->addDefaultsIfNotSet()
		->children()
		->integerNode("lookback_weeks")->defaultValue(4)->min(1)->end()
		->end()
		->end()
		// name => service id, merged over the built-in p95, avg, error_rate and mem. A name
		// listed here can be selected as anomaly.metric - see docs/custom_perf_metric.md
		->arrayNode("metrics")
		->useAttributeAsKey('name')
		->defaultValue([])
		->prototype('scalar')->cannotBeEmpty()->end()
		->end()
		// which detectors app:perf:detect runs
		->arrayNode("detectors")
		->defaultValue(['regression'])
		->prototype('scalar')->cannotBeEmpty()->end()
		->end()
		// name => service id, merged over the built-in regression detector
		->arrayNode("detector_services")
		->useAttributeAsKey('name')
		->defaultValue([])
		->prototype('scalar')->cannotBeEmpty()->end()
		->end()
		->end()
		->end()
		->arrayNode("dashboard_components")
		->defaultValue([
			"StatusList",
			"LogList",
		])
		->prototype('scalar')
		->end()
		->end()
		// the panels of /performance. A page of its own rather than rows on the dashboard: the
		// homepage answers "is anything on fire", and four charts of one project is a different
		// question, asked by somebody who has already clicked that project. Listing a perf panel
		// in dashboard_components is refused at build time - see BugCatcherBundle::loadExtension()
		->arrayNode("performance_components")
		->defaultValue([
			"PerfOverview",
			"PerfTopPaths",
			"PerfDatabase",
		])
		->prototype('scalar')
		->end()
		->end()
		// what a notifier counts, as label => id. The three built-ins are the cases of the switch
		// in EventSubscriber\NotifyCalculateListener; a custom one is a listener of its own on
		// NotifyCalculateEvent plus a name here, which is only what the admin offers as a choice.
		//
		// `perf-regression-count` is the one that does not count every record: the other two are
		// rooted at the hierarchy with no discriminator filter, so a performance regression already
		// raises them - at a threshold somebody chose for log errors. See docs/notifiers.md.
		->arrayNode("notifier_components")
		->defaultValue([
			"Project error count"         => "project-error-count",
			"Same error count"            => "same-error-count",
			"Performance regression count" => "perf-regression-count",
		])
		->prototype('scalar')->end()
		->end()
		->arrayNode("dashboard_list_items")
		->defaultValue([
			RecordLog::class,
			RecordLogTrace::class,
			// a regression is an error somebody has to look at, so it belongs on the page with
			// the other errors - unlike the buckets it was found in, which are a chart
			RecordPerformance::class,
		])
		->prototype('scalar')->end()
		->end()
		->arrayNode("status_list_components")
		->defaultValue([
				"ProjectStatus",
				"LogCount",
				"LogSparkLine",
				"WarningSound",
			]
		)
		->prototype('scalar')->end()
		->end()
		// the row of a project with `perfEnabled` set and the Web workload, when
		// bug_catcher.perf.enabled is also on. The error count stays - it is the thing the
		// dashboard has always been for - and the rest of the twelve columns answer "are the
		// people using this waiting": Apdex says how many of them, p95 says how long, and the
		// sparkline says whether it is getting worse. Adding a cell means taking a column from
		// something else; see worker_status_list_components for the other row
		->arrayNode("perf_status_list_components")
		->defaultValue([
				"ProjectStatus",
				"LogCount",
				"PerfApdex",
				"PerfLatency",
				"PerfSparkLine",
				"WarningSound",
			]
		)
		->prototype('scalar')->end()
		->end()
		// the row of a project whose Project::$perfProfile is Worker - a cron box, a messenger
		// consumer, anything that runs in a loop. No Apdex and no p95, because nobody is waiting
		// on a cron job and both of them say "disaster" every time one does its job: see
		// BugCatcher\Enum\PerfProfile for why moving Apdex's threshold is not an option. What is
		// left are the two cells that can answer a worker - PerfRegressions, which is a judgement
		// against the route's own day-of-week baseline, and PerfThroughput, which goes *down*
		// when the thing stops running. Still twelve columns: 4 + 1 + 2 + 2 + 3
		->arrayNode("worker_status_list_components")
		->defaultValue([
				"ProjectStatus",
				"LogCount",
				"PerfRegressions",
				"PerfThroughput",
				"PerfSparkLine",
				"WarningSound",
			]
		)
		->prototype('scalar')->end()
		->end()
		->arrayNode("collectors")
		->defaultValue([
			'http',
			'messenger',
		])
		->prototype('scalar')
		->end()
		->end()
		->arrayNode("detail_components")
		->useAttributeAsKey('name')
		->arrayPrototype()->scalarPrototype()->end()
		->end()
		->defaultValue([
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
			RecordPerformance::class => [
				'Detail:Header',
				'Detail:Title',
				'Detail:HistoryList',
				'Detail:PerfChart',
			],
		])
		->end()
		->arrayNode("roles")
		->defaultValue([
			"Admin"     => 'ROLE_ADMIN',
			"Developer" => 'ROLE_DEVELOPER',
			"User"      => 'ROLE_USER',
			"Customer"  => 'ROLE_CUSTOMER',
		])
		->prototype('scalar')->end()->end()
		->arrayNode("no_bug_funny_messages")
		->defaultValue([
				"The bugs are hiding under the rug today. But we know where they live.",
				"System clean as a freshly washed commit.",
				"If something doesn’t work, it’s just an illusion – everything’s fine here.",
				"Debugger is resting today. And so can you.",
				"Calm before the storm? Or a final victory?",
				"No bugs? Must be a trick… or a miracle.",
				"Your code just hit the jackpot – bug-free zone!",
				"The robot swept so well even the bugs gave up.",
				"Don’t worry, if there’s no bug here, maybe it’s on vacation.",
				"Code so clean you could eat it with a spoon.",
				"No bugs? You must have become a wizard.",
				"Even the Matrix has glitches… but not here.",
				"Everything runs smoothly, like a merge without conflicts.",
				"This is the moment when you can believe in miracles.",
				"No bugs, just pure programming joy.",
				"Today you don’t need a coffee debug session.",
				"Bugs? Please, those are already legacy.",
				"Code so clean even linters approve it.",
				"No bugs – no excuses.",
				"QA can relax today, everything’s safe.",
				"If there was a bug, you would’ve found it already.",
				"Robot checked every pixel. Nothing.",
				"The code shines brighter than the sun on production.",
				"Bugs are missing, but we don’t miss courage.",
				"Nothing to fix here. Sorry, dev.",
				"Everything works so well it’s getting suspicious.",
				"A bug-free day is a good day.",
				"Nothing broke – are you sure you’re still in the right project?",
				"Even the TODO comments are hiding today.",
				"Congrats, your code would pass grandma’s quality check."
			]
		)
		->prototype('scalar')->end()
		->end()
		->end()
		->end()
    ;
};
