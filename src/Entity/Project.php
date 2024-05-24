<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project {
	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	private ?Uuid $id = null;

	#[ORM\Column(length: 255)]
	private ?string $code = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column]
	private bool $enabled = true;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $url = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $dbConnection = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $pingCollector = null;

	/**
	 * @var Collection<int, User>
	 */
	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'projects')]
	private Collection $users;


	public function __construct() {
		$this->users = new ArrayCollection();
	}

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getCode(): ?string {
		return $this->code;
	}

	public function setCode(string $code): static {
		$this->code = $code;

		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(string $name): static {
		$this->name = $name;

		return $this;
	}

	public function isEnabled(): ?bool {
		return $this->enabled;
	}

	public function setEnabled(bool $enabled): static {
		$this->enabled = $enabled;

		return $this;
	}

	public function getUrl(): ?string {
		return $this->url;
	}

	public function setUrl(string $url): static {
		$this->url = $url;

		return $this;
	}

	public function getDbConnection(): ?string {
		return $this->dbConnection;
	}

	public function setDbConnection(string $dbConnection): static {
		$this->dbConnection = $dbConnection;

		return $this;
	}

	public function getPingCollector(): ?string {
		return $this->pingCollector;
	}

	public function setPingCollector(?string $pingCollector): static {
		$this->pingCollector = $pingCollector;

		return $this;
	}

	/**
	 * @return Collection<int, User>
	 */
	public function getUsers(): Collection {
		return $this->users;
	}

	public function addUser(User $user): static {
		if (!$this->users->contains($user)) {
			$this->users->add($user);
			$user->addProject($this);
		}

		return $this;
	}

	public function removeUser(User $user): static {
		if ($this->users->removeElement($user)) {
			$user->removeProject($this);
		}

		return $this;
	}

	public function __toString(): string {
		return $this->name??'Project';
	}


}
