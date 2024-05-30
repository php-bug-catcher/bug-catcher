<?php

namespace App\Entity;

use App\Entity\Client\Center\Center;
use App\Entity\Client\Client;
use App\Repository\UserRepository;
use App\Validator\NotAbandoned;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface {
	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	private ?Uuid $id = null;

	#[ORM\Column(length: 180)]
	private ?string $email = null;

	/**
	 * @var list<Role> The user roles
	 */
	#[ORM\Column(name: 'roles', type: 'simple_array', nullable: true, enumType: Role::class)]
	private array $enumRoles = [];

	#[ORM\Column]
	private ?string $password = null;

	#[ORM\Column(type: 'boolean')]
	private bool $enabled = false;

	#[ORM\Column(length: 255)]
	private ?string $fullname = null;

	/**
	 * @var Collection<int, Project>
	 */
	#[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'users')]
	private Collection $projects;


	public function __construct() {
		$this->projects = new ArrayCollection();
	}

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getEmail(): ?string {
		return $this->email;
	}

	public function setEmail(string $email): static {
		$this->email = $email;

		return $this;
	}

	/**
	 * @return list<string>
	 * @see UserInterface
	 *
	 */
	public function getRoles(): array {
		$roles   = array_map(fn(Role $role) => $role->value, $this->enumRoles);
		$roles[] = Role::ROLE_USER->value;

		return array_unique($roles);
	}

	public function getEnumRoles(): array {
		return $this->enumRoles;
	}

	/**
	 * @param list<Role> $enumRoles
	 */
	public function setEnumRoles(array $enumRoles): static {
		$this->enumRoles = $enumRoles;

		return $this;
	}

	/**
	 * @see PasswordAuthenticatedUserInterface
	 */
	public function getPassword(): string {
		return $this->password;
	}

	public function setPassword(string $password): static {
		$this->password = $password;

		return $this;
	}

	/**
	 * @see UserInterface
	 */
	public function eraseCredentials(): void {
		// If you store any temporary, sensitive data on the user, clear it here
		// $this->plainPassword = null;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function setEnabled(bool $enabled): static {
		$this->enabled = $enabled;

		return $this;
	}

	public function getFullname(): ?string {
		return $this->fullname;
	}

	public function setFullname(string $fullname): static {
		$this->fullname = $fullname;

		return $this;
	}

	public function __toString(): string {
		return $this->getUserIdentifier();
	}

	/**
	 * A visual identifier that represents this user.
	 *
	 * @see UserInterface
	 */
	public function getUserIdentifier(): string {
		return $this->email;
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


}
