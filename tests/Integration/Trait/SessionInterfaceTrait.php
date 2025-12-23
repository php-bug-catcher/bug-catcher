<?php

namespace BugCatcher\Tests\Integration\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

trait SessionInterfaceTrait {

	public function mockSessionInterface() {
		$sessionStore = [];
		// Create a mock SessionInterface that reads/writes from an in-memory array
		$session = $this->createMock(SessionInterface::class);

		$session->method('get')
			->willReturnCallback(function (string $key, mixed $default = null) use (&$sessionStore) {
				return $sessionStore[$key] ?? $default;
			});

		$session->method('set')
			->willReturnCallback(function (string $key, mixed $value) use (&$sessionStore): void {
				$sessionStore[$key] = $value;
			});

		$session->method('remove')
			->willReturnCallback(function (string $key) use (&$sessionStore): void {
				unset($sessionStore[$key]);
			});

		return $session;
	}

	public function initSession(): void {
		$container = self::getContainer();
		/** @var RequestStack $requestStack */
		$requestStack = $container->get('request_stack');
		$session      = new Session(new MockArraySessionStorage());
		$request      = new Request();
		$request->setSession($session);
		$requestStack->push($request);
	}
}
