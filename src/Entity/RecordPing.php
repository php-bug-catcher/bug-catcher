<?php

namespace PhpSentinel\BugCatcher\Entity;

use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RecordPingRepository::class)]
class RecordPing extends Record
{
    #[ORM\Column(length: 255)]
    private ?string $statusCode = null;

	/**
	 * @param string|null $statusCode
	 */
	public function __construct(Project $project, ?string $statusCode, DateTimeImmutable $date = new DateTimeImmutable()) {
		$this->project    = $project;
		$this->date = $date;
		$this->statusCode = $statusCode;
	}


	public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function setStatusCode(string $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

	function getGroup(): ?string {
		return null;
	}

	function getComponentName(): string {
		return "RecordLog";
	}
}
