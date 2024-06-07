<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotifierRepository::class)]
class NotifierFavicon extends Notifier {

	#[ORM\Column(nullable: false)]
	#[Assert\NotBlank()]
	#[Assert\Length(min: 1, max: 99999)]
	private ?int $importance = 0;

	#[ORM\Column(length: 255, nullable: true)]
	#[Assert\NotBlank()]
	private ?string $component = null;

	public function getImportance(): int {
		return $this->importance;
	}

	public function setImportance(int $importance): self {
		$this->importance = $importance;

		return $this;
	}

	public function getComponent(): ?string {
		return $this->component;
	}

	public function setComponent(?string $component): self {
		$this->component = $component;

		return $this;
	}

}
