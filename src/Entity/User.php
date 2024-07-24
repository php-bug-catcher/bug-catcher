<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Entity\Client\Center\Center;
use PhpSentinel\BugCatcher\Entity\Client\Client;
use PhpSentinel\BugCatcher\Repository\UserRepository;
use PhpSentinel\BugCatcher\Validator\NotAbandoned;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class User implements UserInterface, PasswordAuthenticatedUserInterface {
	private ?Uuid $id = null;

	private ?string $email = null;

	/**
	 * @var string[] The user roles
	 */
	private array $roles = [];

	private ?string $password = null;

	private bool $enabled = false;

	private ?string $fullname = null;

	/**
	 * @var Collection<int, Project>
	 */
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
		$roles   = $this->roles;
		$roles[] = "ROLE_USER";

		return array_unique($roles);
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

	/**
	 * @return Collection<int, Project>
	 */
	public function getActiveProjects(): Collection {
		return $this->projects->matching(
			Criteria::create()->where(Criteria::expr()->eq('enabled', true))
		);
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

	public function setRoles(array $array): static {
		$this->roles = $array;

		return $this;
	}


}
