<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 14:14
 */
namespace BugCatcher\Tests\App;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

class Kernel extends BaseKernel {
	use MicroKernelTrait;


    public function boot(): void
    {
        if (!$this->booted && $this->preBootHandler) {
            call_user_func_array($this->preBootHandler, [$this]);
        }
        parent::boot();
    }

	/**
	 * Gets the path to the bundles configuration file.
	 */
	private function getBundlesPath(): string {
		return __DIR__ . '/config/bundles.php';
	}

	public function getConfigDir() {
        if (!$this->configDir) {
            return __DIR__ . '/config';
        }

        return $this->getCacheDir() . '/' . $this->configDir;
    }


    public function __construct(private readonly ?string $configDir, private mixed $preBootHandler = null)
    {
		parent::__construct('test', true);
	}


	public function getCacheDir(): string {
		return $this->getProjectDir() . '/var/cache/' . $this->environment . '/' . spl_object_hash($this);
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