<?php

namespace PhpSentinel\BugCatcher\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use Symfony\Component\HttpFoundation\Response;

#[ORM\Entity(repositoryClass: RecordPingRepository::class)]
class RecordPing extends Record {
	#[ORM\Column(length: 255)]
	private ?string $statusCode = null;

	/**
	 * @param string|null $statusCode
	 */
	public function __construct(Project $project, ?string $statusCode, DateTimeImmutable $date = new DateTimeImmutable()) {
		$this->project    = $project;
		$this->status = "resolved";
		$this->statusCode = $statusCode;
		parent::__construct($date);
	}


	public function getStatusCode(): ?string {
		return $this->statusCode;
	}

	public function setStatusCode(string $statusCode): static {
		$this->statusCode = $statusCode;

		return $this;
	}

	function calculateHash(): ?string {
		return null;
	}

	function getComponentName(): string {
		return "RecordLog";
	}

	function isError(): bool {
		return $this->getStatus() == Response::HTTP_OK;
	}
}
