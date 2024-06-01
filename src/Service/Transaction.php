<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 6. 2023
 * Time: 8:38
 */

namespace PhpSentinel\BugCatcher\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class Transaction {

	/** @var callable[] */
	private array $rollbackHandlers = [];
	/** @var callable[] */
	private array $commitHandlers = [];

	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly ManagerRegistry        $mr,
	) {}

	public function batchCommit(int $current, int $batchSize): bool {
		if ($current % $batchSize === 0) {
			$this->em->commit();
			foreach ($this->commitHandlers as $handler) {
				$handler();
			}
			$this->begin();

			return true;
		}

		return false;
	}

	public function commit(): void {
		$this->em->commit();
		foreach ($this->commitHandlers as $handler) {
			$handler();
		}
		$this->rollbackHandlers = [];

	}

	public function begin(): void {
		$this->em->beginTransaction();
		$this->rollbackHandlers = [];
		$this->commitHandlers   = [];
	}

	public function flush(): void {
		$this->em->flush();
		$this->rollbackHandlers = [];
	}

	public function rollback(): void {
		$this->em->rollback();
		foreach ($this->rollbackHandlers as $handler) {
			$handler();
		}
	}

	public function addRollbackHandler(callable $handler): void {
		$this->rollbackHandlers[] = $handler;
	}

	public function addCommitHandler(callable $handler): void {
		$this->commitHandlers[] = $handler;
	}

	public function restore(): void {
		$this->mr->resetManager();
	}

}