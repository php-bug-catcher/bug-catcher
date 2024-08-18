<?php

namespace BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use BugCatcher\Repository\NotifierRepository;
use Symfony\Component\Validator\Constraints as Assert;

class NotifierEmail extends Notifier {
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
