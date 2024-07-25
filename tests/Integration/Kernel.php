<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 14:14
 */
namespace PhpSentinel\BugCatcher\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel {
	use MicroKernelTrait {
		MicroKernelTrait::registerContainerConfiguration as registerContainerConfigurationTrait;
	}


	private array $configFiles = [];


	/**
	 * Gets the path to the bundles configuration file.
	 */
	private function getBundlesPath(): string
	{
		return __DIR__ .'/bundles.php';
	}



	public function __construct(private array $options) {
		parent::__construct('test', true);
		$this->addConfigFile(__DIR__ . '/config.yaml');
	}

	public function addConfigFile(string $configFile): void {
		$this->configFiles[] = $configFile;
	}


	public function getCacheDir(): string {
		return $this->getProjectDir() . '/var/cache/' . $this->environment . '/' . spl_object_hash($this);
	}

	public function registerContainerConfiguration(LoaderInterface $loader): void {
		$this->registerContainerConfigurationTrait($loader);
		$loader->load(function (ContainerBuilder $container) use ($loader) {
			foreach ($this->configFiles as $path) {
				$loader->load($path);
			}
			$container->addObjectResource($this);
			$container->loadFromExtension('bug_catcher', $this->options);
		});
	}


}