<?php

namespace App\Entity;

use App\Repository\LogRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogRecordRepository::class)]
#[ORM\Index(name: 'date_idx', columns: ['project_id','date'])]
#[ORM\Index(name: 'done_idx', columns: ['project_id','checked'])]
#[ORM\Index(name: 'message_idx', columns: ['checked', 'message'], options: ["lengths" => [1, 255]])]
class LogRecord extends Record {

	#[ORM\Column]
	private ?bool $checked = null;

	#[ORM\Column(type: Types::TEXT)]
	private ?string $message = null;

	#[ORM\Column(length: 255)]
	private ?string $requestUri = null;

	#[ORM\Column]
	private ?int $level = null;

	private int $count = 1;

	public function isChecked(): ?bool {
		return $this->checked;
	}

	public function setChecked(bool $checked): static {
		$this->checked = $checked;

		return $this;
	}

	public function getMessage(): ?string {
		return $this->message;
	}

	public function setMessage(string $message): static {
		$this->message = $message;

		return $this;
	}

	public function getRequestUri(): ?string {
		return $this->requestUri;
	}

	public function setRequestUri(string $requestUri): static {
		$this->requestUri = $requestUri;

		return $this;
	}

	public function getLevel(): ?int {
		return $this->level;
	}

	public function setLevel(int $level): static {
		$this->level = $level;

		return $this;
	}

	public function getCount(): int {
		return $this->count;
	}

	public function setCount(int $count): self {
		$this->count = $count;

		return $this;
	}


}
