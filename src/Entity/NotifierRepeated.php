<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotifierRepository::class)]
class NotifierRepeated extends Notifier {


	#[ORM\Column(name: '`repeat`', length: 255, enumType: NotifyRepeat::class)]
	protected NotifyRepeat $repeat = NotifyRepeat::Once;

	#[ORM\Column(nullable: true)]
	protected ?int $repeatTime = null;
	#[ORM\Column(length: 255, enumType: NotifyRepeat::class)]
	protected NotifyRepeat $clearAt = NotifyRepeat::Once;

	#[ORM\Column(nullable: true)]
	protected ?int $clearTime = null;


	public function getRepeat(): NotifyRepeat {
		return $this->repeat;
	}

	public function setRepeat(NotifyRepeat $repeat): self {
		$this->repeat = $repeat;

		return $this;
	}

	public function getRepeatTime(): ?int {
		return $this->repeatTime;
	}

	public function setRepeatTime(?int $repeatTime): self {
		$this->repeatTime = $repeatTime;

		return $this;
	}

	public function getClearAt(): NotifyRepeat {
		return $this->clearAt;
	}

	public function setClearAt(NotifyRepeat $clearAt): self {
		$this->clearAt = $clearAt;

		return $this;
	}

	public function getClearTime(): ?int {
		return $this->clearTime;
	}

	public function setClearTime(?int $clearTime): self {
		$this->clearTime = $clearTime;

		return $this;
	}


}
