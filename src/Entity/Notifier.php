<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
	'favicon' => NotifierFavicon::class,
	'sound'   => NotifierSound::class,
	'email'   => NotifierEmail::class,
])]
#[ORM\MappedSuperclass()]
#[ORM\Entity(repositoryClass: NotifierRepository::class)]
abstract class Notifier {
	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	protected ?Uuid $id = null;


	#[ORM\Column(length: 255, nullable: false)]
	#[Assert\NotBlank()]
	#[Assert\Length(min: 3, max: 255)]
	protected ?string $name = null;

	#[ORM\Column(length: 255, enumType: Importance::class)]
	protected Importance $minimalImportance = Importance::Medium;


	#[ORM\Column(length: 255, enumType: NotifyRepeat::class)]
	protected NotifyRepeat $delay = NotifyRepeat::None;

	#[ORM\Column(nullable: true)]
	protected ?int $delayInterval = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	protected ?\DateTimeInterface $lastFailedStatus = null;
	#[ORM\Column()]
	protected int $failedStatusCount = 0;

	#[ORM\Column(name: '`repeat`', length: 255, enumType: NotifyRepeat::class)]
	protected NotifyRepeat $repeat = NotifyRepeat::FrequencyRecords;

	#[ORM\Column(nullable: true)]
	protected ?int $repeatInterval = null;
	#[ORM\Column(length: 255, enumType: NotifyRepeat::class)]
	protected NotifyRepeat $clearAt = NotifyRepeat::FrequencyRecords;

	#[ORM\Column(nullable: true)]
	protected int $repeatAtSkipped = 0;

	#[ORM\Column(nullable: true)]
	protected ?int $clearInterval = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	protected ?\DateTimeInterface $lastNotified = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	protected ?\DateTimeInterface $firstOkStatus = null;

	#[ORM\Column()]
	protected int $lastOkStatusCount = 0;


	public function getRepeat(): NotifyRepeat {
		return $this->repeat;
	}

	public function setRepeat(NotifyRepeat $repeat): self {
		$this->repeat = $repeat;

		return $this;
	}

	public function getRepeatInterval(): ?int {
		return $this->repeatInterval;
	}

	public function setRepeatInterval(?int $repeatInterval): self {
		$this->repeatInterval = $repeatInterval;

		return $this;
	}

	public function getClearAt(): NotifyRepeat {
		return $this->clearAt;
	}

	public function setClearAt(NotifyRepeat $clearAt): self {
		$this->clearAt = $clearAt;

		return $this;
	}

	public function getClearInterval(): ?int {
		return $this->clearInterval;
	}

	public function setClearInterval(?int $clearInterval): self {
		$this->clearInterval = $clearInterval;

		return $this;
	}

	public function getLastNotified(): ?\DateTimeInterface {
		return $this->lastNotified;
	}

	public function setLastNotified(?\DateTimeInterface $lastNotified): self {
		$this->lastNotified = $lastNotified;

		return $this;
	}

	public function getFirstOkStatus(): ?\DateTimeInterface {
		return $this->firstOkStatus;
	}

	public function setFirstOkStatus(?\DateTimeInterface $firstOkStatus): self {
		$this->firstOkStatus = $firstOkStatus;

		return $this;
	}

	/**
	 * @var Collection<int, Project>
	 */
	#[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'users')]
	protected Collection $projects;


	public function __construct() {
		$this->projects = new ArrayCollection();
	}


	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(?string $name): self {
		$this->name = $name;

		return $this;
	}

	public function getMinimalImportance(): ?Importance {
		return $this->minimalImportance;
	}

	public function setMinimalImportance(Importance $minimalImportance): static {
		$this->minimalImportance = $minimalImportance;

		return $this;
	}

	public function getDelay(): NotifyRepeat {
		return $this->delay;
	}

	public function setDelay(NotifyRepeat $delay): self {
		$this->delay = $delay;

		return $this;
	}

	public function getDelayInterval(): ?int {
		return $this->delayInterval;
	}

	public function setDelayInterval(?int $delayInterval): self {
		$this->delayInterval = $delayInterval;

		return $this;
	}

	public function getLastFailedStatus(): ?\DateTimeInterface {
		return $this->lastFailedStatus;
	}

	public function setLastFailedStatus(?\DateTimeInterface $lastFailedStatus): self {
		$this->lastFailedStatus = $lastFailedStatus;

		return $this;
	}


	/**
	 * @return Collection<int, Project>
	 */
	public function getProjects(): Collection {
		return $this->projects;
	}

	public function addProject(Project $project): static {
		if (!$this->projects->contains($project)) {
			$this->projects->add($project);
		}

		return $this;
	}

	public function removeProject(Project $project): static {
		$this->projects->removeElement($project);

		return $this;
	}

	public function __toString(): string {
		return $this->name;
	}

	public function getFailedStatusCount(): int {
		return $this->failedStatusCount;
	}

	public function setFailedStatusCount(int $failedStatusCount): self {
		$this->failedStatusCount = $failedStatusCount;

		return $this;
	}

	public function getRepeatAtSkipped(): int {
		return $this->repeatAtSkipped;
	}

	public function setRepeatAtSkipped(int $repeatAtSkipped): self {
		$this->repeatAtSkipped = $repeatAtSkipped;

		return $this;
	}

	public function getLastOkStatusCount(): int {
		return $this->lastOkStatusCount;
	}

	public function setLastOkStatusCount(int $lastOkStatusCount): self {
		$this->lastOkStatusCount = $lastOkStatusCount;

		return $this;
	}

}
