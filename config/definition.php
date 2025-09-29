<?php

use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogTrace;
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
		->arrayNode("dashboard_components")
		->defaultValue([
			"StatusList",
			"LogList",
		])
		->prototype('scalar')
		->end()
		->end()
		->arrayNode("notifier_components")
		->defaultValue([
			"Project error count" => "project-error-count",
			"Same error count"    => "same-error-count",
		])
		->prototype('scalar')->end()
		->end()
		->arrayNode("dashboard_list_items")
		->defaultValue([
			RecordLog::class,
			RecordLogTrace::class,
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
