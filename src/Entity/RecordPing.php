<?php

namespace BugCatcher\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use BugCatcher\Repository\RecordPingRepository;
use LogicException;
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


	function calculateHash(): ?string {
        throw new LogicException("Ping record does not have hash");
	}

	function getComponentName(): string {
        throw new LogicException("Ping record does not have component name");
	}

	function isError(): bool {
        return $this->statusCode != Response::HTTP_OK;
	}
}
