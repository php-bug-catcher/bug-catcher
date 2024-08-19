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
		->prototype('scalar')->end()->end()
		->arrayNode("dashboard_list_items")
		->defaultValue([
			RecordLog::class,
			RecordLogTrace::class,
		])
		->prototype('scalar')->end()->end()
		->arrayNode("status_list_components")
		->defaultValue([
				"ProjectStatus",
				"LogCount",
				"LogSparkLine",
				"WarningSound",
			]
		)
		->prototype('scalar')->end()->end()
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
			->arrayPrototype()->scalarPrototype()->end()->end()
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
		->end()
        ->end()
    ;
};
