<?php

namespace PhpSentinel\BugCatcher;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html
 */
class BugCatcherBundle extends AbstractBundle {
	public function build(ContainerBuilder $container) {
		parent::build($container);
		$ormCompilerClass = DoctrineOrmMappingsPass::class;
		if (class_exists($ormCompilerClass)) {

			$namespaces        = ['PhpSentinel\BugCatcher',];
			$directories       = [
				realpath(__DIR__ . '/Entity'),
			];
			$managerParameters = [];
			$enabledParameter  = false;
			$aliasMap          = [];
			$container->addCompilerPass(
				DoctrineOrmMappingsPass::createAttributeMappingDriver(
					$namespaces,
					$directories,
					$managerParameters,
					$enabledParameter,
					$aliasMap,
					true
				)
			);
		}
	}


	public function configure(DefinitionConfigurator $definition): void {
		$definition->import('../config/definition.php');
	}

	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
		$container->import('../config/services.php');
	}


}