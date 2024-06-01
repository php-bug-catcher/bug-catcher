<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: RecordRepository::class)]
#[ORM\Index(name: 'date_idx', columns: ['project_id', 'date'])]
#[ORM\Index(name: 'done_idx', columns: ['project_id', 'status'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
	'log'       => RecordLog::class,
	'trace-log' => RecordLogTrace::class,
	'ping'      => RecordPing::class,
])]
#[ORM\MappedSuperclass()]
#[ORM\Index(name: 'full_idx', columns: ['discr','project_id','date','status'])]
#[ORM\Index(name: 'full_idx', columns: ['discr','status','date'])]
abstract class Record {


	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	protected ?Uuid $id = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	#[Assert\NotBlank()]
	protected ?\DateTimeInterface $date = null;

	#[ORM\ManyToOne()]
	#[ORM\JoinColumn(nullable: false)]
	protected ?Project $project = null;

	#[ORM\Column(type: Types::STRING, length: 25)]
	protected string $status = 'new';


	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[Assert\Length(min: 1, max: 50, groups: ['api'])]
	protected ?string $projectCode = null;

	public function __construct() {
		$this->date = new \DateTimeImmutable();
	}

	private int $count = 1;

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getDate(): ?\DateTimeInterface {
		return $this->date;
	}

	public function setDate(\DateTimeInterface $date): static {
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

	abstract function getGroup():?string;

	abstract function getComponentName(): string;

}
