<?php

namespace BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use BugCatcher\Repository\NotifierRepository;

class NotifierSound extends Notifier {

	private string $file;

	public function getFile(): string {
		return $this->file;
	}

	public function setFile(string $file): self {
		$this->file = $file;

		return $this;
	}

}
