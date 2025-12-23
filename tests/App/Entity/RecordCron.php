<?php

namespace BugCatcher\Tests\App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use BugCatcher\Api\Processor\LogRecordSaveProcessor;
use BugCatcher\Entity\Record;
use BugCatcher\Tests\App\Repository\CronRecordRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
	operations: [
		new Post(
			uriTemplate: '/record_cron',
			processor: LogRecordSaveProcessor::class
		),
	],
	denormalizationContext: ['groups' => ['record:write']],
	validationContext: ['groups' => ['api']],
)]
#[ORM\Entity(repositoryClass: CronRecordRepository::class)]
class RecordCron extends Record {
	#[ORM\Column(length: 255)]
	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	private ?string $command = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Groups(['record:write'])]
	#[Assert\NotNull(groups: ['api'])]
	private ?DateTimeInterface $lastStart = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	#[Groups(['record:write'])]
	private ?DateTimeInterface $lastEnd = null;

	#[ORM\Column]
	#[Assert\NotNull(groups: ['api'])]
	#[Groups(['record:write'])]
	#[SerializedName('interval')]
	private ?int $_interval = null;

	#[ORM\Column]
	#[Assert\NotNull(groups: ['api'])]
	#[Groups(['record:write'])]
	private ?int $estimated = null;

	#[ORM\Column(length: 255, nullable: true)]
	#[Assert\Length(max: 255, groups: ['api'])]
	#[Groups(['record:write'])]
	private ?string $lastStatusMessage = null;

	public function getCommand(): ?string {
		return $this->command;
	}

	public function setCommand(string $command): static {
		$this->command = $command;

		return $this;
	}

	public function getLastStart(): ?DateTimeInterface {
		return $this->lastStart;
	}

	public function setLastStart(DateTimeInterface $lastStart): static {
		$this->lastStart = $lastStart;

		return $this;
	}

	public function getLastEnd(): ?DateTimeInterface {
		return $this->lastEnd;
	}

	public function setLastEnd(?DateTimeInterface $lastEnd): static {
		$this->lastEnd = $lastEnd;

		return $this;
	}

	public function getInterval(): ?int {
		return $this->_interval;
	}

	public function setInterval(int $_interval): static {
		$this->_interval = $_interval;

		return $this;
	}

	public function getEstimated(): ?int {
		return $this->estimated;
	}

	public function setEstimated(int $estimated): static {
		$this->estimated = $estimated;

		return $this;
	}

	public function getLastStatusMessage(): ?string {
		return $this->lastStatusMessage;
	}

	public function setLastStatusMessage(string $lastStatusMessage): static {
		$this->lastStatusMessage = $lastStatusMessage;

		return $this;
	}

	function calculateHash(): ?string {
		return $this->command;
	}

	function getComponentName(): string {
		return "RecordCron";
	}

	public function getRequestUri(): string {
		return $this->getCommand();
	}

	public function getMessage(): string {
		$shouldRun     = $this->getLastEnd()->modify("+{$this->getInterval()} minutes");
		$executionTime = $this->getLastEnd()->getTimestamp() - $this->getLastStart()->getTimestamp();
		if (($this->getInterval() > 0 && $shouldRun < new DateTimeImmutable("-5 minutes"))) {
			return "Skript sa neukončil správne. Mal by sa ukončiť do {$this->getInterval()} minút. Posledný štart: {$this->getLastStart()->format("H:i")}";
		}
		if ($this->getEstimated() > 0 && $executionTime >= 0 && $executionTime > $this->getEstimated()) {
			return "Skript bežal dlhšie ako očakávané ({$executionTime}s namiesto {$this->getEstimated()}s)";
		}

		return "Nezisteny stav o ukoncieni skriptu.";
	}

	function isError(): bool {
		return true;
	}
}
