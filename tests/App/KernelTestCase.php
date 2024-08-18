<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 20:53
 */
namespace BugCatcher\Tests\App;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase {

	protected static function bootKernel(array $options = []): KernelInterface {
		static::ensureKernelShutdown();

		$kernel = new Kernel($options);
		$kernel->boot();
		static::$kernel = $kernel;
		static::$booted = true;

		return static::$kernel;
	}

	/**
	 * @param UserInterface        $user
	 * @param array<string, mixed> $tokenAttributes
	 *
	 * @return $this
	 */
	public function loginUser(object $user, string $firewallContext = 'main', array $tokenAttributes = []): static {
		if (!interface_exists(UserInterface::class)) {
			throw new LogicException(sprintf('"%s" requires symfony/security-core to be installed. Try running "composer require symfony/security-core".',
				__METHOD__));
		}

		if (!$user instanceof UserInterface) {
			throw new LogicException(sprintf('The first argument of "%s" must be instance of "%s", "%s" provided.', __METHOD__, UserInterface::class,
				get_debug_type($user)));
		}

		$token = new TestBrowserToken($user->getRoles(), $user, $firewallContext);
		$token->setAttributes($tokenAttributes);

		$container = $this->getContainer();
		$container->get('security.untracked_token_storage')->setToken($token);

		return $this;
	}
}