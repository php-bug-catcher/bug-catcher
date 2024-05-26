<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\LogRecordSaveProcessor;
use App\Repository\LogRecordRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
	operations: [
		new Post(
			processor: LogRecordSaveProcessor::class
		//			securityPostDenormalize: "is_granted('ROLE_CALCULATION_CREATE')",
		),
	],
	denormalizationContext: ['groups' => ['record:write']],
	validationContext: ['groups' => ['api']],
)]
#[ORM\Entity(repositoryClass: LogRecordRepository::class)]
#[ORM\Index(name: 'date_idx', columns: ['project_id', 'date'])]
#[ORM\Index(name: 'done_idx', columns: ['project_id', 'status'])]
#[ORM\Index(name: 'message_idx', columns: ['status', 'message'], options: ["lengths" => [1, 255]])]
class LogRecord extends Record {

	#[ORM\Column(type: Types::STRING, length: 25, enumType: LogRecordStatus::class)]
	private LogRecordStatus $status = LogRecordStatus::NEW;

	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[ORM\Column(type: Types::TEXT)]
	private ?string $message = null;

	#[Groups(['record:write'])]
	#[ORM\Column(length: 255)]
	#[Assert\Length(max: 255, groups: ['api'])]
	private ?string $requestUri = null;

	#[Groups(['record:write'])]
	#[ORM\Column]
	#[Assert\NotBlank(groups: ['api'])]
	private ?int $level = null;


	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[Assert\Length(min: 1, max: 50, groups: ['api'])]
	protected ?string $projectCode = null;

	#[Groups(['record:write'])]
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $stackTrace = null;

	private int $count = 1;

	public function __construct() {
		$this->date = new DateTimeImmutable();
	}


	public function getStatus(): LogRecordStatus {
		return $this->status;
	}

	public function setStatus(LogRecordStatus $status): static {
		$this->status = $status;

		return $this;
	}

	public function getMessage(): ?string {
		return $this->message;
	}

	public function setMessage(string $message): static {
		$this->message = $message;

		return $this;
	}

	public function getRequestUri(): ?string {
		return $this->requestUri;
	}

	public function setRequestUri(string $requestUri): static {
		$this->requestUri = $requestUri;

		return $this;
	}

	public function getLevel(): ?int {
		return $this->level;
	}

	public function setLevel(int $level): static {
		$this->level = $level;

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

	public function getStackTrace(): ?string {
		return $this->stackTrace;
	}

	public function setStackTrace(?string $stackTrace): static {
		$this->stackTrace = $stackTrace;

		return $this;
	}


}
