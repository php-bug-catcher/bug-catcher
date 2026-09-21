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

	/**
	 * What a reader - the detail page, an MCP client - gets to read off any record.
	 *
	 * `RecordLog` keeps all three in columns of its own. A type that has no such thing answers null,
	 * and a type that can work one out - a cron run explaining why it is late - overrides with a
	 * computed value. Not abstract: a subtype that has nothing to say here should not be forced to
	 * say it.
	 *
	 * Deliberately not marked `#[Ignore]`, tempting as it is to keep the nulls out of a subtype's API
	 * response. The serializer merges attribute metadata down the hierarchy, so ignoring the getter
	 * here would ignore the *property* `message` in `RecordLog` as well - the ingest API would stop
	 * reading it off the payload, and every report would fail validation as a blank message.
	 */
	public function getMessage(): ?string {
		return null;
	}

	/**
	 * The monolog level, for a record type that has one: 200 info, 300 warning, 400 error, 500 critical.
	 */
	public function getLevel(): ?int {
		return null;
	}

	public function getRequestUri(): ?string {
		return null;
	}

	abstract function calculateHash(): ?string;

	abstract function getComponentName(): string;

	abstract function isError(): bool;


}
