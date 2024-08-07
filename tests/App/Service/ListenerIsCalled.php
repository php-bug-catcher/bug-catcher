<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 8. 2024
 * Time: 16:55
 */
namespace PhpSentinel\BugCatcher\Tests\App\Service;

class ListenerIsCalled {
	private array $cache = [];

	public function call(string $key): void {
		$this->cache[$key] = true;
	}

	public function isCalled(string $key): bool {
		return $this->cache[$key]??false;
	}

	public function clearAll(): void {
		$this->cache = [];
	}

	public function clear(string $key): void {
		unset($this->cache[$key]);
	}
}