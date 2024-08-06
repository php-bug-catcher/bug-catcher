<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 14:14
 */
namespace PhpSentinel\BugCatcher\Tests\App;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

class Kernel extends BaseKernel {
	use MicroKernelTrait {
		MicroKernelTrait::registerContainerConfiguration as registerContainerConfigurationTrait;
	}


	private array $configFiles = [];


	/**
	 * Gets the path to the bundles configuration file.
	 */
	private function getBundlesPath(): string {
		return __DIR__ . '/config/bundles.php';
	}


	public function __construct(private array $options) {
		parent::__construct('test', true);
		$this->addConfigFile(__DIR__ . '/config/config.yaml');
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

	protected function configureRoutes(RoutingConfigurator $routes): void {
		$paths[] = __DIR__ . '/../../config/routes.php';
		foreach ($paths as $path) {
			if (!file_exists($path)) {
				throw new RuntimeException(sprintf('The file "%s" does not exist.', $path));
			}
			(require $path)($routes->withPath($path), $this);
		}

		$paths = [__DIR__ . '/config/routes.yaml'];
		foreach ($paths as $path) {
			$routes->import($path);
		}
	}

	public function __destruct() {
		//remove entire cache dir recursively
		$this->removeDir($this->getCacheDir());
	}

	private function removeDir(string $dir): void {
		$files = scandir($dir);
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			if (is_dir($dir . '/' . $file)) {
				$this->removeDir($dir . '/' . $file);
			} else {
				unlink($dir . '/' . $file);
			}
		}
		rmdir($dir);
	}


}