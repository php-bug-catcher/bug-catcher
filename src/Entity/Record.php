<?php

namespace PhpSentinel\BugCatcher\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


abstract class Record {


	protected ?Uuid $id = null;

	#[Assert\NotBlank()]
	protected ?DateTimeInterface $date = null;

	protected ?Project $project = null;

	protected string $status = 'new';

	protected ?string $hash = null;


	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[Assert\Length(min: 1, max: 50, groups: ['api'])]
	protected ?string $projectCode = null;

	public function __construct(DateTimeInterface $date = null) {
		if ($date) {
			$this->date = $date;
		} else {
			$this->date = new DateTimeImmutable();
		}
	}

	private int $count = 1;

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getDate(): ?DateTimeInterface {
		return $this->date;
	}

	public function setDate(DateTimeInterface $date): static {
		$this->date = $date;

		return $this;
	}

	public function getProject(): ?Project {
		return $this->project;
	}

	public function setProject(?Project $project): static {
		$this->project = $project;

		return $this;
	}


	public function getStatus(): string {
		return $this->status;
	}

	public function setStatus(string $status): static {
		$this->status = $status;

		return $this;
	}


	public function getCount(): int {
		return $this->count;
	}

	public function setCount(int $count): self {
		$this->count = $count;

		return $this;
	}

	public function getProjectCode(): ?string {
		return $this->projectCode;
	}

	public function setProjectCode(?string $projectCode): self {
		$this->projectCode = $projectCode;

		return $this;
	}

	public function getHash(): ?string {
		return $this->hash;
	}

	public function setHash(?string $hash): self {
		$this->hash = $hash;

		return $this;
	}

	abstract function calculateHash(): ?string;

	abstract function getComponentName(): string;

}
