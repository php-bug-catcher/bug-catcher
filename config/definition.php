<?php

use PhpSentinel\BugCatcher\Entity\RecordLog;
use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
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
		->prototype('scalar')->end()
		->defaultValue([
			"StatusList",
			"LogList",
		])->end()
		->arrayNode("notifier_components")
		->prototype('scalar')->end()
		->defaultValue([
			"Project error count" => "project-error-count",
			"Same error count"    => "same-error-count",
		])->end()
		->arrayNode("dashboard_list_items")
		->prototype('scalar')->end()
		->defaultValue([
			RecordLog::class,
			RecordLogTrace::class,
		])->end()
		->arrayNode("status_list_components")
		->prototype('scalar')->end()
		->defaultValue([
				"ProjectStatus",
				"LogCount",
				"LogSparkLine",
				"WarningSound",
			]
		)->end()
		->arrayNode("detail_components")
		->prototype('scalar')->end()
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
		])->end()
		->arrayNode("collectors")
		->prototype('scalar')->end()
		->defaultValue([
			'http',
			'messenger',
		])->end()
		->arrayNode("roles")
		->prototype('scalar')->end()
		->defaultValue([
			"Admin"     => 'ROLE_ADMIN',
			"Developer" => 'ROLE_DEVELOPER',
			"User"      => 'ROLE_USER',
			"Customer"  => 'ROLE_CUSTOMER',
		])->end()
            ->end()
        ->end()
    ;
};
