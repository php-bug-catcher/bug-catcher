<?php

namespace BugCatcher\Entity;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeImmutable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


abstract class Record {


	protected ?Uuid $id = null;

	#[Assert\NotBlank()]
	protected ?DateTimeImmutable $date            = null;

	#[Assert\NotNull(groups: ['Default'])]
	protected ?Project $project = null;

	protected string $status = 'new';

	protected ?string $hash = null;

	#[Groups(['record:write'])]
	#[Assert\Length(max: 15, groups: ['api'])]
	protected ?string $code = null;


	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[Assert\Length(min: 1, max: 50, groups: ['api'])]
	protected ?string $projectCode = null;

    #[Groups(['record:write'])]
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        "additionalProperties" => [
            "type" => "string"
        ]
    ])]
    public ?array $metadata = null;

	public function __construct(?DateTimeImmutable $date = null) {
		if ($date) {
			$this->date = $date;
		} else {
			$this->date = new DateTimeImmutable();
		}
	}

	private int $count = 1;
	private ?DateTimeImmutable   $firstOccurrence = null;

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getDate(): ?DateTimeImmutable {
		return $this->date;
	}

	public function setDate(DateTimeImmutable $date): static {
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

	public function getFirstOccurrence(): ?DateTimeImmutable
    {
        return $this->firstOccurrence;
    }

	public function setFirstOccurrence(?DateTimeImmutable $firstOccurrence): self
    {
        $this->firstOccurrence = $firstOccurrence;
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

	public function getCode(): ?string {
		return $this->code;
	}

	public function setCode(?string $code): self {
		$this->code = $code;

		return $this;
	}

	abstract function calculateHash(): ?string;

	abstract function getComponentName(): string;

	abstract function isError(): bool;


}
