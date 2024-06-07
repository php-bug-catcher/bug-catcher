<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotifierRepository::class)]
class NotifierEmail extends Notifier {
	#[ORM\Column(length: 255, nullable: false)]
	#[Assert\NotBlank()]
	#[Assert\Length(min: 3, max: 255)]
	private ?string $email = null;

	public function getEmail(): ?string {
		return $this->email;
	}

	public function setEmail(?string $email): static {
		$this->email = $email;

		return $this;
	}
}
