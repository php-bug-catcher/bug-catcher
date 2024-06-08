<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;

#[ORM\Entity(repositoryClass: NotifierRepository::class)]
class NotifierSound extends Notifier {

	#[ORM\Column(type: "string", length: 255)]
	private string $file;

	public function getFile(): string {
		return $this->file;
	}

	public function setFile(string $file): self {
		$this->file = $file;

		return $this;
	}

}
