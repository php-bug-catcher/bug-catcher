<?php

namespace BugCatcher\Tests\App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use BugCatcher\Api\Processor\LogRecordSaveProcessor;
use BugCatcher\Entity\Record;
use BugCatcher\Mcp\HasMcpDetails;
use BugCatcher\Tests\App\Repository\CronRecordRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
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
class RecordCron extends Record implements HasMcpDetails {
	#[ORM\Column(length: 255)]
	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	private ?string $command = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	#[Groups(['record:write'])]
	#[Assert\NotNull(groups: ['api'])]
	private ?DateTimeImmutable $lastStart = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	#[Groups(['record:write'])]
	private ?DateTimeImmutable $lastEnd = null;

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

	public function getLastStart(): ?DateTimeImmutable {
		return $this->lastStart;
	}

	public function setLastStart(DateTimeImmutable $lastStart): static {
		$this->lastStart = $lastStart;

		return $this;
	}

	public function getLastEnd(): ?DateTimeImmutable {
		return $this->lastEnd;
	}

	public function setLastEnd(?DateTimeImmutable $lastEnd): static {
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

	/**
	 * Every run of one command in one project is the same entry, the way two reports of one exception
	 * are. The start time is deliberately left out: including it would give every run a hash of its own
	 * and nothing would ever group.
	 *
	 * The column is 32 characters wide, hence the hash rather than the command itself.
	 */
	function calculateHash(): ?string {
		return md5(join('-', [$this->project?->getId()?->toHex(), $this->command]));
	}

	function getComponentName(): string {
		return "RecordCron";
	}

	public function getRequestUri(): string {
		return $this->getCommand();
	}

	public function getMessage(): string {
		return match ($this->state()) {
			self::STATE_UNFINISHED => sprintf(
				'The command did not finish. It should be done within %d minutes. Last start: %s',
				$this->_interval, $this->lastStart?->format('H:i') ?? '?',
			),
			self::STATE_TOO_SLOW   => sprintf(
				'The command ran longer than expected (%ds instead of %ds)',
				$this->runtimeSeconds(), $this->estimated,
			),
			default                => 'Nothing is known about how the command ended.',
		};
	}

	/**
	 * @return array<string, scalar|null>
	 */
	#[Ignore]
	public function getMcpDetails(): array {
		return [
			'command'           => $this->command,
			'lastStart'         => $this->lastStart?->format('Y-m-d H:i:s'),
			'lastEnd'           => $this->lastEnd?->format('Y-m-d H:i:s'),
			'intervalMinutes'   => $this->_interval,
			'estimatedSeconds'  => $this->estimated,
			'runtimeSeconds'    => $this->runtimeSeconds(),
			'state'             => $this->state(),
			'lastStatusMessage' => $this->lastStatusMessage,
		];
	}

	public const STATE_UNFINISHED = 'unfinished';
	public const STATE_TOO_SLOW   = 'too-slow';
	public const STATE_UNKNOWN    = 'unknown';

	private function runtimeSeconds(): ?int {
		return $this->lastStart === null || $this->lastEnd === null
			? null
			: $this->lastEnd->getTimestamp() - $this->lastStart->getTimestamp();
	}

	/**
	 * Shared by the message a person reads and the `state` an MCP client reads, so the two cannot
	 * drift apart.
	 */
	private function state(): string {
		$interval = $this->_interval ?? 0;
		// a run that never reported an end has no end to count from, so the deadline runs from the
		// start - which is also what the message says out loud
		$deadlineFrom = $this->lastEnd ?? $this->lastStart;
		if ($interval > 0 && $deadlineFrom !== null
			&& $deadlineFrom->modify("+{$interval} minutes") < new DateTimeImmutable('-5 minutes')) {
			return self::STATE_UNFINISHED;
		}
		$runtime = $this->runtimeSeconds();
		if (($this->estimated ?? 0) > 0 && $runtime !== null && $runtime > $this->estimated) {
			return self::STATE_TOO_SLOW;
		}

		return self::STATE_UNKNOWN;
	}

	function isError(): bool {
		return true;
	}
}
