<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
	'favicon'  => NotifierFavicon::class,
	'sound'    => NotifierSound::class,
	'email'    => NotifierEmail::class,
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


}
